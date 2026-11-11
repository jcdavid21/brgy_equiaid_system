"""Conservative spatial agreement for flood segmentation predictions."""

import cv2
import numpy as np

CNN_PIXEL_THRESHOLD = 0.70
YOLO_CONFIDENCE_THRESHOLD = 0.70
MIN_MASK_IOU = 0.30
MIN_FLOOD_COVERAGE = 12.0


def flood_detections(result, shape):
    """Rasterize normalized polygons so letterbox padding cannot shift masks."""
    mask = np.zeros(shape, dtype=np.uint8)
    indices = []
    confidences = []
    if result.boxes is None or result.masks is None:
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
    """Require both detectors and every available CNN to support the same region.

    These are conservative inference defaults, not calibrated accuracy claims.
    Coverage is measured only on pixels supported by all available models.
    """
    empty = np.zeros(nano_mask.shape, dtype=bool)
    if not predictions or mask_iou(nano_mask, small_mask) < MIN_MASK_IOU:
        return empty

    detector_mask = nano_mask & small_mask
    confirmed = detector_mask.copy()
    for probabilities in predictions:
        if probabilities.shape != confirmed.shape or not np.isfinite(probabilities).all():
            return empty
        cnn_mask = probabilities >= CNN_PIXEL_THRESHOLD
        if mask_iou(cnn_mask, detector_mask) < MIN_MASK_IOU:
            return empty
        confirmed &= cnn_mask

    if np.mean(confirmed) * 100 < MIN_FLOOD_COVERAGE:
        return empty
    return confirmed
