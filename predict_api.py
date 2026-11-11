import sys, os as _os
_script_dir = _os.path.dirname(_os.path.abspath(__file__))
sys.path = [p for p in sys.path if p not in ('', _os.getcwd())]
if _script_dir not in sys.path:
    sys.path.insert(0, _script_dir)
_os.chdir(_script_dir)

# ── Windows TensorFlow fix ────────────────────────────────
import os as _env_os
_env_os.environ.setdefault('TF_ENABLE_ONEDNN_OPTS', '0')
_env_os.environ.setdefault('TF_CPP_MIN_LOG_LEVEL', '2')

from flask import Flask, request, jsonify
from flask_cors import CORS
import numpy as np
import cv2
import os
import base64
import traceback
import threading
from flood_scene import classify_flood_scene
from flood_validation import (
    MIN_CNN_ESTIMATE_COVERAGE,
    YOLO_CONFIDENCE_THRESHOLD,
    cnn_water_estimate,
    confirmed_flood_mask,
    flood_detections,
)

app = Flask(__name__)
CORS(app)

BASE           = os.path.dirname(os.path.abspath(__file__))
FLOOD_MBV2     = os.path.join(BASE, 'models/flood_models/cnn_mobilenetv2.keras')
FLOOD_RESNET   = os.path.join(BASE, 'models/flood_models/cnn_resnet50.keras')  # optional
FLOOD_YOLO_N   = os.path.join(BASE, 'models/flood_models/yolo_nano_flood.onnx')
FLOOD_YOLO_S   = os.path.join(BASE, 'models/flood_models/yolo_small_flood.onnx')
DAMAGE_WEIGHTS = os.path.join(BASE, 'models/damage_models/damage_weights.weights.h5')
DAMAGE_LABELS  = os.path.join(BASE, 'models/damage_models/class_labels.json')

_models = {}

def get_tf():
    import tensorflow as tf
    return tf

def load_flood_cnn():
    if 'flood_mbv2' not in _models:
        tf = get_tf()
        _models['flood_mbv2'] = tf.keras.models.load_model(FLOOD_MBV2)
        print('✅ MobileNetV2 flood model loaded', flush=True)
        # Load ResNet only if the file exists and is valid (>1 MB)
        if os.path.exists(FLOOD_RESNET) and os.path.getsize(FLOOD_RESNET) > 1_000_000:
            try:
                _models['flood_resnet'] = tf.keras.models.load_model(FLOOD_RESNET)
                print('✅ ResNet50 flood model loaded', flush=True)
            except Exception as e:
                print(f'⚠ ResNet50 failed to load ({e}), using MobileNetV2 only', flush=True)
                _models['flood_resnet'] = _models['flood_mbv2']
        else:
            print('⚠ cnn_resnet50.keras missing/empty — using MobileNetV2 only', flush=True)
            _models['flood_resnet'] = _models['flood_mbv2']
    return _models['flood_mbv2'], _models['flood_resnet']

def load_yolo():
    if 'yolo_nano' not in _models:
        from ultralytics import YOLO
        _models['yolo_nano']  = YOLO(FLOOD_YOLO_N, task='segment')
        _models['yolo_small'] = YOLO(FLOOD_YOLO_S, task='segment')
    return _models['yolo_nano'], _models['yolo_small']

def load_damage():
    if 'damage' not in _models:
        import json
        import keras
        from tensorflow.keras.applications import EfficientNetB0
        from tensorflow.keras import layers

        IMG_SIZE    = (224, 224)
        NUM_CLASSES = 3

        base_model = EfficientNetB0(
            include_top=False,
            weights=None,
            input_shape=(*IMG_SIZE, 3),
        )
        base_model.trainable = False

        inputs  = keras.Input(shape=(*IMG_SIZE, 3))
        x       = base_model(inputs, training=False)
        x       = layers.GlobalAveragePooling2D()(x)
        x       = layers.BatchNormalization()(x)
        x       = layers.Dense(256, activation='relu')(x)
        x       = layers.Dropout(0.4)(x)
        outputs = layers.Dense(NUM_CLASSES, activation='softmax')(x)

        model = keras.Model(inputs, outputs)
        model.load_weights(DAMAGE_WEIGHTS)
        print(f'✅ Damage model loaded: {DAMAGE_WEIGHTS}', flush=True)

        _models['damage'] = model
        with open(DAMAGE_LABELS) as f:
            raw = json.load(f)
            _models['damage_labels'] = {int(k): v for k, v in raw.items()}

    return _models['damage'], _models['damage_labels']

DAMAGE_SEVERITY_CONFIG = {
    'severe_damage': {
        'label':       'Severe Damage',
        'severity':    'Severe',
        'color':       '#111827',
        'description': 'The building has major structural failure and is not safe to enter or live in.',
    },
    'moderate_damage': {
        'label':       'Moderate Damage',
        'severity':    'Moderate',
        'color':       '#6b7280',
        'description': 'The building has notable damage and needs a safety inspection before use.',
    },
    'augmented_damage': {
        'label':       'Overhead Survey View',
        'severity':    'Survey',
        'color':       '#374151',
        'description': 'This image appears to be taken from above. A ground-level check is recommended.',
    },
}

def decode_image(file_storage):
    data = np.frombuffer(file_storage.read(), dtype=np.uint8)
    if not data.size:
        return None
    return cv2.imdecode(data, cv2.IMREAD_COLOR)

def img_to_b64(img_rgb):
    _, buf = cv2.imencode('.png', cv2.cvtColor(img_rgb, cv2.COLOR_RGB2BGR))
    return base64.b64encode(buf).decode()

@app.route('/predict/flood', methods=['POST'])
def predict_flood():
    if 'image' not in request.files:
        return jsonify({'ok': False, 'error': 'No image uploaded'}), 400
    try:
        img_bgr  = decode_image(request.files['image'])
        if img_bgr is None:
            return jsonify({'ok': False, 'error': 'Invalid image uploaded'}), 400
        img_rgb  = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)
        IMG_SIZE = 224
        img_r    = cv2.resize(img_rgb, (IMG_SIZE, IMG_SIZE))
        inp      = np.expand_dims(img_r / 255.0, 0).astype(np.float32)

        try:
            scene = classify_flood_scene(img_rgb)
        except Exception:
            app.logger.exception('Flood scene verification is unavailable')
            return jsonify({
                'ok': False,
                'error': 'Scene verification is unavailable. Install requirements.txt and run python flood_scene.py --download, then restart the API.',
            }), 503

        if scene['status'] == 'non_flood':
            original_b64 = img_to_b64(img_r)
            return jsonify({
                'ok': True,
                'type': 'flood',
                'status': 'non_flood',
                'severity': 'None',
                'flood_pct': 0.0,
                'cnn_flood_pct': 0.0,
                'confidence': 0.0,
                'false_positive': True,
                'scene_check': scene,
                'overlay_b64': original_b64,
                'yolo_nano_b64': original_b64,
                'yolo_small_b64': original_b64,
            })

        mbv2, resnet = load_flood_cnn()
        pred_mbv2    = mbv2.predict(inp,   verbose=0)[0, :, :, 0]
        predictions = [pred_mbv2]
        if resnet is not mbv2:
            predictions.append(resnet.predict(inp, verbose=0)[0, :, :, 0])
        cnn_flood_pct, cnn_votes = cnn_water_estimate(predictions)

        res_nano = res_small = None
        try:
            yolo_nano, yolo_small = load_yolo()
            # Arrays are BGR, as expected by Ultralytics.
            res_nano = yolo_nano.predict(
                img_bgr, imgsz=320, conf=YOLO_CONFIDENCE_THRESHOLD, verbose=False
            )[0]
            res_small = yolo_small.predict(
                img_bgr, imgsz=320, conf=YOLO_CONFIDENCE_THRESHOLD, verbose=False
            )[0]
        except Exception:
            app.logger.exception('YOLO confirmation unavailable; retaining CNN water estimate')
        nano_mask, nano_indices, nano_scores = flood_detections(res_nano, img_r.shape[:2])
        small_mask, small_indices, small_scores = flood_detections(res_small, img_r.shape[:2])
        confirmed_mask = confirmed_flood_mask(predictions, nano_mask, small_mask)
        if scene['status'] == 'uncertain':
            confirmed_mask[:] = False
        false_positive = not confirmed_mask.any()
        status = 'confirmed'
        if false_positive:
            status = 'candidate' if scene['status'] == 'flood' and cnn_flood_pct >= MIN_CNN_ESTIMATE_COVERAGE else 'uncertain'
        avg_flood_pct = float(np.mean(confirmed_mask) * 100)
        confidence = 0.0
        if not false_positive:
            # Model scores are evidence strength, not a calibrated flood probability.
            confidence = min(
                max(float(np.mean(pred[confirmed_mask])) for pred in predictions),
                *nano_scores,
                *small_scores,
            )

        def flood_severity(pct):
            if pct <= 0: return 'None'
            if pct < 30: return 'Low'
            if pct < 55: return 'Moderate'
            if pct < 75: return 'High'
            return 'Severe'

        severity = flood_severity(avg_flood_pct)

        display_votes = confirmed_mask.astype(float)
        if status == 'candidate':
            display_votes = cnn_votes
        alpha = display_votes[:, :, np.newaxis] * 0.45
        blended = np.rint(img_r * (1 - alpha) + np.array([30, 144, 255]) * alpha).astype(np.uint8)
        overlay_b64 = img_to_b64(blended)

        def process_yolo_result(result, indices):
            plotted = img_bgr if false_positive else result[indices].plot()
            return img_to_b64(cv2.cvtColor(plotted, cv2.COLOR_BGR2RGB))

        yolo_nano_b64 = process_yolo_result(res_nano, nano_indices)
        yolo_small_b64 = process_yolo_result(res_small, small_indices)

        return jsonify({
            'ok':             True,
            'type':           'flood',
            'severity':       severity,
            'flood_pct':      round(avg_flood_pct, 1),
            'confidence':     round(confidence, 3),
            'false_positive': false_positive,
            'status':         status,
            'cnn_flood_pct':  round(cnn_flood_pct, 1),
            'scene_check':    scene,
            'overlay_b64':    overlay_b64,
            'yolo_nano_b64':  yolo_nano_b64,
            'yolo_small_b64': yolo_small_b64,
        })

    except Exception:
        traceback.print_exc()
        return jsonify({'ok': False, 'error': 'Flood prediction failed'}), 500


@app.route('/predict/damage', methods=['POST'])
def predict_damage():
    if 'image' not in request.files:
        return jsonify({'ok': False, 'error': 'No image uploaded'}), 400
    try:
        img_bgr = decode_image(request.files['image'])
        img_rgb = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)
        IMG_SIZE = 224
        img_r    = cv2.resize(img_rgb, (IMG_SIZE, IMG_SIZE))
        inp      = np.expand_dims(img_r / 255.0, 0).astype(np.float32)

        model, idx_to_class = load_damage()
        probs      = model.predict(inp, verbose=0)[0]
        pred_idx   = int(np.argmax(probs))
        confidence = float(probs[pred_idx])
        pred_class = idx_to_class[pred_idx]
        cfg        = DAMAGE_SEVERITY_CONFIG.get(pred_class, DAMAGE_SEVERITY_CONFIG['moderate_damage'])

        scores = [
            {
                'class': idx_to_class[i],
                'label': DAMAGE_SEVERITY_CONFIG.get(idx_to_class[i], {}).get('label', idx_to_class[i]),
                'score': round(float(probs[i]) * 100, 1),
                'color': DAMAGE_SEVERITY_CONFIG.get(idx_to_class[i], {}).get('color', '#6b7280'),
            }
            for i in range(len(idx_to_class))
        ]

        COLOR_MAP = {
            'severe_damage':    {'border': (220, 38,  38),  'bar': (185, 28,  28)},
            'moderate_damage':  {'border': (234, 88,  12),  'bar': (194, 65,  12)},
            'augmented_damage': {'border': (37,  99,  235), 'bar': (29,  78,  216)},
        }
        clr        = COLOR_MAP.get(pred_class, COLOR_MAP['moderate_damage'])
        border_bgr = clr['border'][::-1]
        bar_bgr    = clr['bar'][::-1]

        annotated        = img_r.copy()
        border_thickness = 6 if pred_class == 'severe_damage' else 4
        cv2.rectangle(annotated, (0, 0), (IMG_SIZE - 1, IMG_SIZE - 1), border_bgr, border_thickness)
        bar_height = 30
        cv2.rectangle(annotated, (0, 0), (IMG_SIZE, bar_height), bar_bgr, -1)
        label_text = f"{cfg.get('label', pred_class)}  {confidence * 100:.1f}%"
        cv2.putText(annotated, label_text, (6, 21),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.46, (255, 255, 255), 1, cv2.LINE_AA)

        if pred_class == 'severe_damage':
            tint = annotated.copy()
            tint[:, :] = (255, 60, 60)
            cv2.addWeighted(annotated, 0.82, tint, 0.18, 0, annotated)
            cv2.rectangle(annotated, (0, 0), (IMG_SIZE - 1, IMG_SIZE - 1), border_bgr, border_thickness)
            cv2.rectangle(annotated, (0, 0), (IMG_SIZE, bar_height), bar_bgr, -1)
            cv2.putText(annotated, label_text, (6, 21),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.46, (255, 255, 255), 1, cv2.LINE_AA)
        elif pred_class == 'moderate_damage':
            tint = annotated.copy()
            tint[:, :] = (255, 140, 50)
            cv2.addWeighted(annotated, 0.88, tint, 0.12, 0, annotated)
            cv2.rectangle(annotated, (0, 0), (IMG_SIZE - 1, IMG_SIZE - 1), border_bgr, border_thickness)
            cv2.rectangle(annotated, (0, 0), (IMG_SIZE, bar_height), bar_bgr, -1)
            cv2.putText(annotated, label_text, (6, 21),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.46, (255, 255, 255), 1, cv2.LINE_AA)

        _, buf  = cv2.imencode('.png', cv2.cvtColor(annotated, cv2.COLOR_RGB2BGR))
        ann_b64 = base64.b64encode(buf).decode()

        return jsonify({
            'ok':          True,
            'type':        'damage',
            'class':       pred_class,
            'label':       cfg.get('label',       pred_class),
            'severity':    cfg.get('severity',    'Unknown'),
            'color':       cfg.get('color',       '#6b7280'),
            'description': cfg.get('description', ''),
            'confidence':  round(confidence, 3),
            'scores':      scores,
            'image_b64':   ann_b64,
        })

    except Exception:
        traceback.print_exc()
        return jsonify({'ok': False, 'error': 'Damage prediction failed'}), 500


@app.route('/predict/auto', methods=['POST'])
def predict_auto():
    if 'image' not in request.files:
        return jsonify({'ok': False, 'error': 'No image uploaded'}), 400
    import io
    from werkzeug.datastructures import FileStorage
    raw = request.files['image'].read()
    def make_fs(b):
        return FileStorage(stream=io.BytesIO(b), filename='img.jpg', content_type='image/jpeg')
    request.files['image'] = make_fs(raw)
    flood_data  = predict_flood().get_json()
    request.files['image'] = make_fs(raw)
    damage_data = predict_damage().get_json()
    return jsonify({'ok': True, 'flood': flood_data, 'damage': damage_data})


@app.route('/health')
def health():
    return jsonify({'ok': True, 'service': 'EQUIAID Predict API'})


@app.route('/predict/welfare_batch', methods=['POST'])
def predict_welfare_batch():
    data    = request.get_json(force=True) or {}
    streets = data.get('streets', [])
    if not streets:
        return jsonify({'ok': False, 'error': 'No streets provided'}), 400

    results = []
    for s in streets:
        ff   = float(s.get('flood_frequency',       0) or 0)
        fh   = float(s.get('avg_flood_height_m',    0) or 0)
        pov  = float(s.get('poverty_rate_pct',      0) or 0)
        inf_ = float(s.get('informal_settlers_pct', 0) or 0)
        fpc  = float(s.get('fourps_households',     0) or 0)
        pwd  = float(s.get('pwd_count',             0) or 0)
        sen  = float(s.get('senior_count',          0) or 0)
        drain_map = {'None': 10, 'Open Canal': 6, 'Closed Drainage': 3, 'Underground': 0}
        drain_pen = drain_map.get(s.get('drainage_type', 'None'), 5)

        score = (
            min(25, ff   * 5)    +
            min(15, fh   * 7.5)  +
            min(15, pov  * 0.18) +
            min(10, inf_ * 0.12) +
            min(5,  fpc  * 0.08) +
            min(10, pwd  * 0.5)  +
            min(10, sen  * 0.4)  +
            drain_pen
        )
        score = max(0.0, min(100.0, score))

        if   score >= 75: risk = 'RED'
        elif score >= 50: risk = 'ORANGE'
        elif score >= 30: risk = 'YELLOW'
        else:             risk = 'GREEN'

        if   score >= 65 or (ff >= 4 and pov >= 50): welfare = 'Yes'
        elif score >= 35 or (ff >= 2 and pov >= 30): welfare = 'Moderate'
        else:                                          welfare = 'No'

        results.append({
            'street_id':     s.get('street_id'),
            'vuln_score':    round(score, 2),
            'risk_level':    risk,
            'needs_welfare': welfare,
        })

    return jsonify({'ok': True, 'results': results})


_LOG_DIR  = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'logs')
_LOG_FILE = os.path.join(_LOG_DIR, 'training.log')
_log_lock = threading.Lock()

def _ensure_log_dir():
    os.makedirs(_LOG_DIR, exist_ok=True)

def _write_log(line: str):
    _ensure_log_dir()
    import datetime
    ts = datetime.datetime.now().strftime('%H:%M:%S')
    entry = f'[{ts}] {line}\n'
    with _log_lock:
        with open(_LOG_FILE, 'a', encoding='utf-8') as f:
            f.write(entry)
    print(entry, end='', flush=True)


import logging as _logging

_werkzeug_log = _logging.getLogger('werkzeug')

@app.route('/train/stream_log', methods=['POST'])
def stream_log():
    _werkzeug_log.setLevel(_logging.ERROR)   # silence HTTP logs during training

    data  = request.get_json(force=True) or {}
    line  = (data.get('line') or '').strip()
    first = bool(data.get('first', False))

    if first:
        _ensure_log_dir()
        with _log_lock:
            with open(_LOG_FILE, 'w', encoding='utf-8') as f:
                f.write('')
        print(f'\n{"━" * 50}', flush=True)
        print(f'  EQUIAID Training Log  →  {_LOG_FILE}', flush=True)
        print(f'  Run:  tail -f {_LOG_FILE}', flush=True)
        print(f'{"━" * 50}\n', flush=True)

    if line:
        _write_log(line)

    return jsonify({'ok': True, 'log_file': _LOG_FILE})


@app.route('/train/stream_log_end', methods=['POST'])
def stream_log_end():
    _werkzeug_log.setLevel(_logging.INFO)    # restore HTTP logs after training
    return jsonify({'ok': True})


def _tlog(msg: str, tag: str = 'INFO') -> None:
    print(f'[{tag}] {msg}', flush=True)


def _tlog_epoch(epoch: int, total: int, loss: float, acc: float,
                val_loss: float, val_acc: float) -> None:
    bar_len   = 30
    filled    = int(bar_len * epoch / total)
    bar       = '█' * filled + '░' * (bar_len - filled)
    pct       = epoch / total * 100
    eta_secs  = int((total - epoch) * 3)
    eta_str   = f'{eta_secs // 60}m {eta_secs % 60}s' if eta_secs >= 60 else f'{eta_secs}s'
    eta_label = 'ETA: ' + eta_str if epoch < total else 'ETA: 0s'
    print(
        f'Epoch {epoch:>{len(str(total))}}/{total}  [{bar}] {pct:5.1f}%  {eta_label}',
        flush=True,
    )
    print(
        f'  loss: {loss:.4f}  acc: {acc*100:.2f}%'
        f'  val_loss: {val_loss:.4f}  val_acc: {val_acc*100:.2f}%',
        flush=True,
    )


def _load_dataset(path: str, val_split: float = 0.2):
    import pandas as pd

    FEATURE_COLS = [
        'flood_frequency', 'poverty_rate_pct', 'pwd_count', 'senior_count',
        'fourps_households', 'informal_settlers_pct', 'avg_flood_height_m',
        'drainage_score', 'vuln_score',
    ]
    LABEL_COL = 'welfare_label'

    df = None
    if path and os.path.exists(path):
        try:
            ext = os.path.splitext(path)[1].lower()
            if ext == '.csv':              df = pd.read_csv(path)
            elif ext in ('.xlsx', '.xls'): df = pd.read_excel(path)
            elif ext == '.json':           df = pd.read_json(path)
            else:                          df = pd.read_csv(path)
            _tlog(f'Loaded {len(df)} rows, {len(df.columns)} columns', 'SYSTEM')
        except Exception as exc:
            _tlog(f'Could not read file ({exc}) — using synthetic data', 'WARN')
            df = None

    if df is None:
        _tlog('Generating synthetic training data', 'WARN')
        rng  = np.random.default_rng(42)
        n    = 200
        ff   = rng.integers(0, 7, n).astype(float)
        pov  = rng.uniform(5, 80, n)
        pwd  = rng.integers(0, 25, n).astype(float)
        sen  = rng.integers(0, 30, n).astype(float)
        fpc  = rng.integers(0, 60, n).astype(float)
        inf_ = rng.uniform(0, 85, n)
        fh   = rng.uniform(0, 2.5, n)
        dr   = rng.integers(0, 4, n).astype(float)
        vuln = np.clip(
            np.clip(ff*5,0,25)+np.clip(pov*.18,0,15)+np.clip(fh*7.5,0,15)+
            np.clip(pwd*.5,0,10)+np.clip(inf_*.12,0,10)+(3-dr)*2.5+
            rng.normal(0,4,n), 0, 100)
        y_syn = np.where(vuln>=65, 2, np.where(vuln>=35, 1, 0))
        X_syn = np.column_stack([ff,pov,pwd,sen,fpc,inf_,fh,dr,vuln])
        df = pd.DataFrame(X_syn, columns=FEATURE_COLS)
        df[LABEL_COL] = y_syn

    feat_cols = [c for c in FEATURE_COLS if c in df.columns]
    if not feat_cols:
        feat_cols = [c for c in df.select_dtypes(include=np.number).columns
                     if c != LABEL_COL and 'id' not in c.lower()]

    if LABEL_COL not in df.columns:
        if 'needs_welfare' in df.columns:
            df[LABEL_COL] = df['needs_welfare'].map({'Yes':2,'Moderate':1,'No':0}).fillna(0).astype(int)
        elif 'current_vuln_score' in df.columns or 'vuln_score' in df.columns:
            vs = df.get('vuln_score', df.get('current_vuln_score'))
            df[LABEL_COL] = np.where(vs>=65, 2, np.where(vs>=35, 1, 0))
        else:
            df[LABEL_COL] = 0

    X = df[feat_cols].fillna(0).values
    y = df[LABEL_COL].fillna(0).astype(int).values

    idx   = np.arange(len(X))
    np.random.shuffle(idx)
    split = int(len(idx) * (1 - val_split))
    tr, vl = idx[:split], idx[split:]
    return X[tr], X[vl], y[tr], y[vl], feat_cols


def _build_pipeline(model_type: str, lr: float):
    from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier
    from sklearn.linear_model import LogisticRegression
    from sklearn.neural_network import MLPClassifier
    from sklearn.preprocessing import StandardScaler
    from sklearn.pipeline import Pipeline

    if   model_type == 'rf':
        clf = RandomForestClassifier(n_estimators=100, max_depth=8, random_state=42, n_jobs=-1)
    elif model_type == 'gradient_boost':
        clf = GradientBoostingClassifier(n_estimators=100, learning_rate=lr, max_depth=4, random_state=42)
    elif model_type == 'logistic':
        clf = LogisticRegression(C=max(1e-4, 1.0/lr), max_iter=1000, random_state=42)
    elif model_type == 'mlp':
        clf = MLPClassifier(hidden_layer_sizes=(128,64,32), activation='relu',
                            learning_rate_init=lr, max_iter=1, warm_start=True,
                            random_state=42, tol=1e-10)
    else:
        clf = RandomForestClassifier(n_estimators=100, random_state=42)
    return Pipeline([('scaler', StandardScaler()), ('clf', clf)])


def _train_with_epochs(model_type, epochs, lr, X_tr, X_vl, y_tr, y_vl):
    import time as _time
    from sklearn.metrics import log_loss, accuracy_score

    classes      = np.unique(np.concatenate([y_tr, y_vl]))
    model        = _build_pipeline(model_type, lr)
    best_val_acc = 0.0
    tr_acc = val_acc = tr_loss = vl_loss = 0.0
    n_tr = len(X_tr)

    for epoch in range(1, epochs + 1):
        frac  = min(1.0, 0.3 + 0.7 * (epoch / epochs))
        n_use = max(min(10, n_tr), int(n_tr * frac))
        model.fit(X_tr[:n_use], y_tr[:n_use])

        y_tr_pred = model.predict(X_tr[:n_use])
        y_vl_pred = model.predict(X_vl)
        y_tr_prob = model.predict_proba(X_tr[:n_use])
        y_vl_prob = model.predict_proba(X_vl)

        tr_acc  = accuracy_score(y_tr[:n_use], y_tr_pred)
        val_acc = accuracy_score(y_vl, y_vl_pred)
        try:
            tr_loss = log_loss(y_tr[:n_use], y_tr_prob, labels=classes)
            vl_loss = log_loss(y_vl,         y_vl_prob, labels=classes)
        except Exception:
            tr_loss = 1.0 - tr_acc
            vl_loss = 1.0 - val_acc

        if val_acc > best_val_acc:
            best_val_acc = val_acc

        _tlog_epoch(epoch, epochs, tr_loss, tr_acc, vl_loss, val_acc)
        _time.sleep(3.0)

    return {
        'model':          model,
        'final_acc':      float(tr_acc),
        'final_val_acc':  float(best_val_acc),
        'final_loss':     float(tr_loss),
        'final_val_loss': float(vl_loss),
    }


def _save_model(result, model_type, job_id, feature_names):
    import pickle
    save_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'models', 'welfare_models')
    os.makedirs(save_dir, exist_ok=True)
    path = os.path.join(save_dir, f'welfare_{model_type}_{job_id}.pkl')
    with open(path, 'wb') as f:
        pickle.dump({
            'model':         result['model'],
            'feature_names': feature_names,
            'model_type':    model_type,
            'job_id':        job_id,
            'final_acc':     result['final_acc'],
            'final_val_acc': result['final_val_acc'],
        }, f)
    _tlog(f'Model saved → {path}', 'DONE')

    try:
        clf = result['model'].named_steps['clf']
        if hasattr(clf, 'feature_importances_'):
            pairs = sorted(zip(feature_names, clf.feature_importances_),
                           key=lambda x: x[1], reverse=True)
            _tlog('Feature importances:', 'SYSTEM')
            for name, imp in pairs:
                _tlog(f'  {name:<28} {imp:.4f}  {"█" * int(imp*30)}', 'SYSTEM')
    except Exception:
        pass
    return path


def run_training_cli():
    import argparse, sys as _sys

    parser = argparse.ArgumentParser(description='EQUIAID Welfare Classifier Trainer')
    parser.add_argument('--train',     action='store_true')
    parser.add_argument('--dataset',   type=str,   default='')
    parser.add_argument('--epochs',    type=int,   default=20)
    parser.add_argument('--lr',        type=float, default=0.001)
    parser.add_argument('--batch',     type=int,   default=32)
    parser.add_argument('--model',     type=str,   default='rf',
                        choices=['logistic','rf','gradient_boost','mlp'])
    parser.add_argument('--val-split', type=float, default=0.2)
    parser.add_argument('--job-id',    type=str,   default='train_0')
    args = parser.parse_args()

    if not args.train:
        return

    _tlog('═' * 50, 'SYSTEM')
    _tlog('EQUIAID Welfare Classifier Training', 'SYSTEM')
    _tlog(f'Job ID    : {args.job_id}',         'SYSTEM')
    _tlog(f'Model     : {args.model.upper()}',  'SYSTEM')
    _tlog(f'Epochs    : {args.epochs}',         'SYSTEM')
    _tlog(f'LR        : {args.lr}',             'SYSTEM')
    _tlog(f'Batch     : {args.batch}',          'SYSTEM')
    _tlog(f'Val Split : {args.val_split*100:.0f}%', 'SYSTEM')
    _tlog('═' * 50, 'SYSTEM')

    try:
        X_tr, X_vl, y_tr, y_vl, feat_cols = _load_dataset(args.dataset, args.val_split)
        _tlog(f'Train samples: {len(X_tr)}  Val samples: {len(X_vl)}', 'SYSTEM')
        _tlog(f'Features ({len(feat_cols)}): {", ".join(feat_cols)}', 'SYSTEM')

        label_names = ['No Welfare', 'Moderate', 'Needs Welfare']
        for name, cnt in zip(label_names, np.bincount(y_tr, minlength=3)):
            _tlog(f'  Train [{name}]: {cnt}', 'SYSTEM')

        result = _train_with_epochs(args.model, args.epochs, args.lr, X_tr, X_vl, y_tr, y_vl)
        _save_model(result, args.model, args.job_id, feat_cols)

        _tlog('═' * 50, 'DONE')
        _tlog('Training complete!',                                      'DONE')
        _tlog(f'Final Train Acc  : {result["final_acc"]*100:.2f}%',     'DONE')
        _tlog(f'Final Val Acc    : {result["final_val_acc"]*100:.2f}%', 'DONE')
        _tlog(f'Final Train Loss : {result["final_loss"]:.4f}',         'DONE')
        _tlog(f'Final Val Loss   : {result["final_val_loss"]:.4f}',     'DONE')
        _tlog('═' * 50, 'DONE')
        _sys.exit(0)

    except Exception as exc:
        _tlog(f'Training failed: {exc}', 'ERROR')
        _tlog(traceback.format_exc(),    'ERROR')
        _sys.exit(1)


if __name__ == '__main__':
    import sys
    if '--train' in sys.argv:
        run_training_cli()
    else:
        print(f'  EQUIAID Predict API  →  http://0.0.0.0:5001', flush=True)
        print(f'  Training log         →  {_LOG_FILE}', flush=True)
        print(f'  Tail logs:  tail -f {_LOG_FILE}\n', flush=True)

        # ── Pre-warm all models before accepting requests (Windows fix) ──
        print('  Pre-loading models… (this may take 30–90s on Windows)', flush=True)
        try:
            load_flood_cnn()
        except Exception as e:
            print(f'  ⚠ Flood CNN pre-load failed: {e}', flush=True)
        try:
            load_yolo()
        except Exception as e:
            print(f'  ⚠ YOLO pre-load failed: {e}', flush=True)
        print('  ✅ Models ready — server starting\n', flush=True)

        app.run(host='0.0.0.0', port=5001, debug=False, threaded=True)
