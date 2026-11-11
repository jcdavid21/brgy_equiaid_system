"""Validate detector agreement without treating CNN disagreement as no flood."""

import cv2
import numpy as np

CNN_PIXEL_THRESHOLD = 0.50
YOLO_CONFIDENCE_THRESHOLD = 0.50
MIN_MASK_IOU = 0.30
MIN_FLOOD_COVERAGE = 5.0
MIN_CNN_SUPPORT = 0.30
MIN_CNN_ESTIMATE_COVERAGE = 12.0


def cnn_water_estimate(predictions):
    """Preserve the original mean CNN coverage separately from confirmation.

    The vote map allows an overlay to show partial model agreement without
    presenting these segmentation scores as a calibrated flood classifier.
    """
    masks = [
        probabilities >= CNN_PIXEL_THRESHOLD
        for probabilities in predictions
        if probabilities.ndim == 2 and np.isfinite(probabilities).all()
    ]
    if not masks:
        raise ValueError('No valid CNN segmentation output')
    votes = np.mean(masks, axis=0)
    coverage = float(np.mean(votes) * 100)
    return coverage, votes


def flood_detections(result, shape):
    """Rasterize normalized polygons so letterbox padding cannot shift masks."""
    mask = np.zeros(shape, dtype=np.uint8)
    indices = []
    confidences = []
    if result is None or result.boxes is None or result.masks is None:
        return mask.astype(bool), indices, confidences

    height, width = shape
    for index, polygon in enumerate(result.masks.xyn):
        score = float(result.boxes.conf[index].item())
        class_id = int(result.boxes.cls[index].item())
        label = result.names.get(class_id, '').lower().replace('_', ' ').strip()
        if label not in {'flood', 'flood water', 'floodwater'}:
            continue
        if not np.isfinite(score) or score < YOLO_CONFIDENCE_THRESHOLD:
            continue
        if len(polygon) < 3 or not np.isfinite(polygon).all():
            continue
        points = np.rint(np.asarray(polygon) * [width - 1, height - 1])
        points = np.clip(points, [0, 0], [width - 1, height - 1]).astype(np.int32)
        cv2.fillPoly(mask, [points], 1)
        indices.append(index)
        confidences.append(score)
    return mask.astype(bool), indices, confidences


def mask_iou(first, second):
    union = np.count_nonzero(first | second)
    return float(np.count_nonzero(first & second) / union) if union else 0.0


def confirmed_flood_mask(predictions, nano_mask, small_mask):
    """Use agreeing YOLO masks, corroborated by at least one available CNN.

    CNN support is measured within the detector region, rather than using IoU
    against a potentially much larger CNN mask. A disagreeing auxiliary CNN must
    not erase an otherwise supported detection. These scores are not calibrated.
    """
    empty = np.zeros(nano_mask.shape, dtype=bool)
    if not predictions or mask_iou(nano_mask, small_mask) < MIN_MASK_IOU:
        return empty

    detector_mask = nano_mask & small_mask
    supported = False
    for probabilities in predictions:
        if probabilities.shape != detector_mask.shape or not np.isfinite(probabilities).all():
            continue
        cnn_mask = probabilities >= CNN_PIXEL_THRESHOLD
        if np.mean(cnn_mask[detector_mask]) >= MIN_CNN_SUPPORT:
            supported = True

    if not supported or np.mean(detector_mask) * 100 < MIN_FLOOD_COVERAGE:
        return empty
    return detector_mask
