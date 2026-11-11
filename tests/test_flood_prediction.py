import base64
import io
import sys
import unittest
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

import cv2
import numpy as np

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import predict_api
from flood_validation import cnn_water_estimate, confirmed_flood_mask, flood_detections


def detection(score=0.9, label='Flood Water', polygon=None):
    if polygon is None:
        polygon = [[0, 0.5], [1, 0.5], [1, 1], [0, 1]]
    return SimpleNamespace(
        boxes=SimpleNamespace(conf=np.array([score]), cls=np.array([0])),
        masks=SimpleNamespace(xyn=[np.array(polygon)]),
        names={0: label},
    )


class FloodValidationTests(unittest.TestCase):
    def setUp(self):
        self.water = np.zeros((224, 224), dtype=bool)
        self.water[112:, :] = True
        self.probabilities = np.where(self.water, 0.9, 0.1)

    def test_cnn_coverage_alone_cannot_confirm_flood(self):
        empty = np.zeros_like(self.water)
        self.assertFalse(confirmed_flood_mask([self.probabilities], empty, empty).any())

    def test_original_cnn_mean_coverage_is_preserved_without_yolo(self):
        first = np.zeros((10, 10))
        second = np.zeros((10, 10))
        first[:6] = 0.9
        second[:4] = 0.9
        coverage, votes = cnn_water_estimate([first, second])
        self.assertEqual(coverage, 50)
        self.assertEqual(votes[4, 0], 0.5)

    def test_one_detector_is_insufficient(self):
        self.assertFalse(confirmed_flood_mask(
            [self.probabilities], self.water, np.zeros_like(self.water)
        ).any())

    def test_disjoint_detections_are_rejected(self):
        self.assertFalse(confirmed_flood_mask(
            [self.probabilities], self.water, ~self.water
        ).any())

    def test_cnn_and_detectors_must_agree_spatially(self):
        self.assertFalse(confirmed_flood_mask(
            [1 - self.probabilities], self.water, self.water
        ).any())

    def test_auxiliary_cnn_disagreement_does_not_erase_supported_detection(self):
        np.testing.assert_array_equal(confirmed_flood_mask(
            [self.probabilities, 1 - self.probabilities], self.water, self.water
        ), self.water)

    def test_broad_cnn_mask_can_support_smaller_detector_region(self):
        np.testing.assert_array_equal(confirmed_flood_mask(
            [np.full(self.water.shape, 0.9)], self.water, self.water
        ), self.water)

    def test_uncertain_and_nonfinite_cnn_are_rejected(self):
        for value in [0.4, np.nan]:
            with self.subTest(value=value):
                self.assertFalse(confirmed_flood_mask(
                    [np.full(self.water.shape, value)], self.water, self.water
                ).any())

    def test_small_regions_do_not_confirm_flood(self):
        small = np.zeros_like(self.water)
        small[200:, :100] = True
        self.assertFalse(confirmed_flood_mask(
            [np.where(small, 0.9, 0.1)], small, small
        ).any())

    def test_ten_percent_flood_region_is_not_erased_by_old_coverage_floor(self):
        small = np.zeros_like(self.water)
        small[200:, :] = True
        np.testing.assert_array_equal(confirmed_flood_mask(
            [np.where(small, 0.9, 0.1)], small, small
        ), small)

    def test_agreeing_models_preserve_positive_region(self):
        mask = confirmed_flood_mask([self.probabilities], self.water, self.water)
        np.testing.assert_array_equal(mask, self.water)

    def test_rejects_low_confidence_wrong_class_and_missing_masks(self):
        for result in [detection(0.49), detection(np.nan), detection(label='Person'),
                       SimpleNamespace(boxes=None, masks=None)]:
            with self.subTest(result=result):
                mask, indices, scores = flood_detections(result, self.water.shape)
                self.assertFalse(mask.any())
                self.assertEqual(indices, [])
                self.assertEqual(scores, [])

    def test_normalized_polygons_align_with_cnn_coordinates(self):
        mask, indices, scores = flood_detections(detection(), self.water.shape)
        np.testing.assert_array_equal(mask, self.water)
        self.assertEqual(indices, [0])
        self.assertEqual(scores, [0.9])

    def test_medium_confidence_flood_detection_is_kept(self):
        mask, indices, scores = flood_detections(detection(0.653), self.water.shape)
        self.assertTrue(mask.any())
        self.assertEqual(indices, [0])
        self.assertEqual(scores, [0.653])


class FloodEndpointTests(unittest.TestCase):
    def setUp(self):
        self.client = predict_api.app.test_client()
        scene_patch = patch.object(predict_api, 'classify_flood_scene', return_value={
            'status': 'flood', 'margin': 0.06,
        })
        self.scene_check = scene_patch.start()
        self.addCleanup(scene_patch.stop)

    def upload(self, image):
        _, encoded = cv2.imencode('.png', image)
        return self.client.post('/predict/flood', data={
            'image': (io.BytesIO(encoded.tobytes()), 'synthetic.png'),
        })

    def test_missing_and_invalid_upload(self):
        self.assertEqual(self.client.post('/predict/flood').status_code, 400)
        response = self.client.post('/predict/flood', data={
            'image': (io.BytesIO(b'not an image'), 'invalid.png'),
        })
        self.assertEqual(response.status_code, 400)
        response = self.client.post('/predict/flood', data={
            'image': (io.BytesIO(b''), 'empty.png'),
        })
        self.assertEqual(response.status_code, 400)

    @patch.object(predict_api, 'load_flood_cnn')
    @patch.object(predict_api, 'load_yolo')
    def test_non_flood_scene_is_rejected_before_coverage_estimation(self, yolo, cnn):
        self.scene_check.return_value = {'status': 'non_flood', 'margin': -0.1}
        image = np.full((150, 300, 3), (100, 30, 20), dtype=np.uint8)
        response = self.upload(image)
        result = response.get_json()
        self.assertEqual(response.status_code, 200)
        self.assertEqual(result['status'], 'non_flood')
        self.assertEqual(result['flood_pct'], 0)
        self.assertEqual(result['cnn_flood_pct'], 0)
        cnn.assert_not_called()
        yolo.assert_not_called()
        original = cv2.imdecode(np.frombuffer(base64.b64decode(result['overlay_b64']), dtype=np.uint8), cv2.IMREAD_COLOR)
        np.testing.assert_array_equal(original, cv2.resize(image, (224, 224)))

    def test_missing_scene_classifier_cannot_silently_restore_false_positives(self):
        self.scene_check.side_effect = RuntimeError('synthetic missing scene model')
        with self.assertLogs(predict_api.app.logger, level='ERROR'):
            response = self.upload(np.full((150, 300, 3), 120, dtype=np.uint8))
        self.assertEqual(response.status_code, 503)
        self.assertFalse(response.get_json()['ok'])

    @patch.object(predict_api, 'load_yolo')
    @patch.object(predict_api, 'load_flood_cnn')
    def test_missing_detector_evidence_preserves_cnn_candidate(self, cnn, yolo):
        model = SimpleNamespace(predict=lambda *args, **kwargs: np.full((1, 224, 224, 1), 0.95))
        cnn.return_value = (model, model)
        detector = SimpleNamespace(predict=lambda *args, **kwargs: [
            SimpleNamespace(boxes=None, masks=None)
        ])
        yolo.return_value = (detector, detector)
        image = np.full((150, 300, 3), (100, 30, 20), dtype=np.uint8)
        response = self.upload(image)
        self.assertEqual(response.status_code, 200)
        result = response.get_json()
        self.assertTrue(result['false_positive'])
        self.assertEqual(result['severity'], 'None')
        self.assertEqual(result['flood_pct'], 0)
        self.assertEqual(result['confidence'], 0)
        self.assertEqual(result['status'], 'candidate')
        self.assertEqual(result['cnn_flood_pct'], 100)

    @patch.object(predict_api, 'load_yolo')
    @patch.object(predict_api, 'load_flood_cnn')
    def test_ambiguous_scene_does_not_become_a_candidate_from_coverage_alone(self, cnn, yolo):
        self.scene_check.return_value = {'status': 'uncertain', 'margin': -0.01}
        model = SimpleNamespace(predict=lambda *args, **kwargs: np.full((1, 224, 224, 1), 0.95))
        cnn.return_value = (model, model)
        detector = SimpleNamespace(predict=lambda *args, **kwargs: [
            SimpleNamespace(boxes=None, masks=None)
        ])
        yolo.return_value = (detector, detector)
        result = self.upload(np.full((150, 300, 3), 120, dtype=np.uint8)).get_json()
        self.assertEqual(result['status'], 'uncertain')
        self.assertEqual(result['confidence'], 0)
        self.assertEqual(result['flood_pct'], 0)

    @patch.object(predict_api, 'load_yolo', side_effect=RuntimeError('synthetic unavailable detector'))
    @patch.object(predict_api, 'load_flood_cnn')
    def test_optional_yolo_failure_does_not_disable_original_cnn(self, cnn, yolo):
        model = SimpleNamespace(predict=lambda *args, **kwargs: np.full((1, 224, 224, 1), 0.9))
        cnn.return_value = (model, model)
        with self.assertLogs(predict_api.app.logger, level='ERROR'):
            response = self.upload(np.full((150, 300, 3), 120, dtype=np.uint8))
        result = response.get_json()
        self.assertEqual(response.status_code, 200)
        self.assertEqual(result['status'], 'candidate')
        self.assertEqual(result['cnn_flood_pct'], 100)
        self.assertEqual(result['confidence'], 0)

    @patch.object(predict_api, 'load_yolo')
    @patch.object(predict_api, 'load_flood_cnn')
    def test_agreeing_models_keep_grey_and_muddy_water(self, cnn, yolo):
        probabilities = np.full((1, 224, 224, 1), 0.1)
        probabilities[:, 112:, :, :] = 0.95
        model = SimpleNamespace(predict=lambda *args, **kwargs: probabilities)
        cnn.return_value = (model, model)

        class Result:
            def __init__(self, image):
                self.__dict__.update(detection().__dict__)
                self.image = image

            def __getitem__(self, indices):
                return self

            def plot(self):
                return self.image

        detector = SimpleNamespace(predict=lambda image, **kwargs: [Result(image)])
        yolo.return_value = (detector, detector)
        for color in [(120, 120, 120), (70, 100, 130)]:
            with self.subTest(color=color):
                image = np.full((150, 300, 3), color, dtype=np.uint8)
                result = self.upload(image).get_json()
                self.assertFalse(result['false_positive'])
                self.assertEqual(result['status'], 'confirmed')
                self.assertEqual(result['flood_pct'], 50)
                # Coverage (50%) must never substitute for model confidence (90%).
                self.assertEqual(result['confidence'], 0.9)


if __name__ == '__main__':
    unittest.main()
