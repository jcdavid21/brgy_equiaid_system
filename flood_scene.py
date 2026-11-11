"""Local scene verification before displaying flood segmentation estimates.

CLIP similarity is supporting evidence, not a calibrated flood probability.
Run `python flood_scene.py --download` once to prepare the pinned local weights.
"""

import json
from pathlib import Path
import threading

import numpy as np

MODEL_ID = 'openai/clip-vit-base-patch32'
MODEL_REVISION = '3d74acf9a28c67741b2f4f2ea7635f0aaf6f0268'
MODEL_CACHE = Path(__file__).resolve().parent / 'models' / 'scene_models'
NON_FLOOD_MARGIN = 0.02

FLOOD_PROMPTS = (
    'a photo of a flooded street with water covering the road.',
    'a photo of people wading through floodwater.',
    'a photo of a flood rescue in a flooded neighborhood.',
    'a photo of houses and roads inundated by flooding.',
    'a photo of grey muddy floodwater covering a town.',
)
NON_FLOOD_PROMPTS = (
    'a photo of a logo, seal, emblem or illustration.',
    'a photo of an indoor gathering of people, with no floodwater.',
    'a photo of people registering for aid at a table.',
    'a photo of a street protest with people holding signs.',
    'a photo of a dry street or buildings, without flooding.',
    'a photo of a river or lake in its normal banks.',
    'a photo of a swimming pool.',
    'a photo of a document or computer screen.',
    'a photo of ordinary rain on a wet road, without flooding.',
)

_scene_model = None
_load_lock = threading.Lock()


def scene_pixel_values(image_rgb):
    """Match the pinned CLIP processor's resize, center crop, and normalization."""
    from PIL import Image

    image = Image.fromarray(image_rgb).convert('RGB')
    width, height = image.size
    if width <= height:
        size = (224, int(height * 224 / width))
    else:
        size = (int(width * 224 / height), 224)
    image = image.resize(size, resample=Image.Resampling.BICUBIC)
    left = (image.width - 224) // 2
    top = (image.height - 224) // 2
    image = image.crop((left, top, left + 224, top + 224))
    pixels = (np.asarray(image).astype(np.float64) / 255.0).astype(np.float32)
    mean = np.array([0.48145466, 0.4578275, 0.40821073], dtype=np.float32)
    std = np.array([0.26862954, 0.26130258, 0.27577711], dtype=np.float32)
    return np.ascontiguousarray(((pixels - mean) / std).transpose(2, 0, 1)[None])


def scene_decision(similarities):
    scores = np.asarray(similarities, dtype=float)
    if scores.shape != (len(FLOOD_PROMPTS) + len(NON_FLOOD_PROMPTS),):
        raise ValueError('Unexpected scene classifier output shape')
    if not np.isfinite(scores).all():
        raise ValueError('Invalid scene classifier scores')

    margin = float(scores[:len(FLOOD_PROMPTS)].max() - scores[len(FLOOD_PROMPTS):].max())
    if margin <= -NON_FLOOD_MARGIN:
        status = 'non_flood'
    elif margin > 0:
        status = 'flood'
    else:
        status = 'uncertain'
    return {'status': status, 'margin': round(margin, 4)}


def load_scene_model():
    global _scene_model
    with _load_lock:
        if _scene_model is not None:
            return _scene_model

        import onnxruntime as ort

        metadata = json.loads((MODEL_CACHE / 'scene-model.json').read_text())
        if metadata.get('revision') != MODEL_REVISION or metadata.get('prompts') != list(FLOOD_PROMPTS + NON_FLOOD_PROMPTS):
            raise ValueError('Scene model artifacts do not match this version; prepare them again')
        text_features = np.load(MODEL_CACHE / 'scene-text-features.npy', allow_pickle=False)
        if text_features.shape != (len(FLOOD_PROMPTS + NON_FLOOD_PROMPTS), 512) or not np.isfinite(text_features).all():
            raise ValueError('Invalid scene text features')
        options = ort.SessionOptions()
        options.intra_op_num_threads = 2
        options.inter_op_num_threads = 1
        model = ort.InferenceSession(
            str(MODEL_CACHE / 'clip-vision-int8.onnx'),
            sess_options=options,
            providers=['CPUExecutionProvider'],
        )
        _scene_model = (model, text_features)
        return _scene_model


def classify_flood_scene(image_rgb):
    model, text_features = load_scene_model()
    features = model.run(['image_features'], {'pixel_values': scene_pixel_values(image_rgb)})[0]
    features = features / np.linalg.norm(features, axis=-1, keepdims=True)
    similarities = (features @ text_features.T)[0]
    return scene_decision(similarities)


if __name__ == '__main__':
    import argparse

    parser = argparse.ArgumentParser(description='Prepare the local flood scene classifier.')
    parser.add_argument('--download', action='store_true', help='Download the pinned official weights.')
    arguments = parser.parse_args()
    if arguments.download:
        try:
            load_scene_model()
        except Exception:
            from prepare_flood_scene import prepare_scene_model

            prepare_scene_model()
    load_scene_model()
    print(f'Flood scene model ready: {MODEL_ID} ({MODEL_REVISION})')
