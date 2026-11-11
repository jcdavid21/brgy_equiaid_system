"""One-time preparation of the small, local ONNX scene-verification runtime."""

import json
import os

os.environ.setdefault('USE_TF', '0')
os.environ.setdefault('HF_HUB_DISABLE_TELEMETRY', '1')
os.environ.setdefault('HF_HUB_DISABLE_XET', '1')

from flood_scene import FLOOD_PROMPTS, NON_FLOOD_PROMPTS, MODEL_CACHE, MODEL_ID, MODEL_REVISION


def prepare_scene_model():
    import numpy as np
    import torch
    from transformers import CLIPModel, CLIPProcessor
    from onnxruntime.quantization import QuantType, quantize_dynamic

    MODEL_CACHE.mkdir(parents=True, exist_ok=True)
    options = {'revision': MODEL_REVISION, 'cache_dir': str(MODEL_CACHE), 'token': False}
    print('Preparing pinned official CLIP weights locally...', flush=True)
    processor = CLIPProcessor.from_pretrained(MODEL_ID, use_fast=False, **options)
    model = CLIPModel.from_pretrained(
        MODEL_ID, use_safetensors=False, weights_only=True, attn_implementation='eager', **options
    ).eval()
    with torch.inference_mode():
        inputs = processor(text=FLOOD_PROMPTS + NON_FLOOD_PROMPTS, return_tensors='pt', padding=True)
        text = model.get_text_features(**inputs)
        text = (text / text.norm(dim=-1, keepdim=True)).cpu().numpy()

    class VisionEncoder(torch.nn.Module):
        def __init__(self, clip):
            super().__init__()
            self.clip = clip

        def forward(self, pixel_values):
            return self.clip.get_image_features(pixel_values=pixel_values)

    float_path = MODEL_CACHE / 'clip-vision-fp32.onnx'
    quantized_path = MODEL_CACHE / 'clip-vision-int8.tmp.onnx'
    print('Exporting the vision encoder (text features are cached separately)...', flush=True)
    with torch.inference_mode():
        torch.onnx.export(
            VisionEncoder(model), torch.zeros(1, 3, 224, 224), str(float_path),
            input_names=['pixel_values'], output_names=['image_features'],
            opset_version=17, dynamo=False,
        )
    print('Quantizing vision matrix weights for the CPU runtime...', flush=True)
    quantize_dynamic(
        str(float_path), str(quantized_path), weight_type=QuantType.QInt8,
        op_types_to_quantize=['MatMul', 'Gemm'],
    )
    os.replace(quantized_path, MODEL_CACHE / 'clip-vision-int8.onnx')
    text_path = MODEL_CACHE / 'scene-text-features.tmp.npy'
    np.save(text_path, text)
    os.replace(text_path, MODEL_CACHE / 'scene-text-features.npy')
    metadata_path = MODEL_CACHE / 'scene-model.tmp.json'
    metadata_path.write_text(json.dumps({
        'revision': MODEL_REVISION,
        'prompts': list(FLOOD_PROMPTS + NON_FLOOD_PROMPTS),
    }, indent=2) + '\n')
    os.replace(metadata_path, MODEL_CACHE / 'scene-model.json')
    print('Prepared quantized local scene model.', flush=True)
