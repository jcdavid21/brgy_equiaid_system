# Barangay Equity Aid System

A comprehensive disaster management and aid distribution system for barangays, featuring AI-powered flood and damage prediction, real-time monitoring, and resource allocation capabilities.

## 📋 Table of Contents

- [System Overview](#system-overview)
- [Prerequisites](#prerequisites)
- [Installation Guide](#installation-guide)
- [Configuration](#configuration)
- [Running the System](#running-the-system)
- [Project Structure](#project-structure)
- [Features](#features)
- [API Endpoints](#api-endpoints)
- [Troubleshooting](#troubleshooting)

## 🎯 System Overview

The Barangay Equity Aid System is a full-stack web application that helps barangays manage disaster response and aid distribution. It includes:

- **User Dashboard**: View announcements, reports, assistance requests, and disaster maps
- **Admin Portal**: Manage users, resources, activities, and analytics
- **AI Predictions**: Real-time flood and building damage detection using machine learning
- **Street Monitoring**: Monitor disaster status by street/area
- **Resource Allocation**: Distribute resources based on need assessment

## 📦 Prerequisites

### System Requirements
- **OS**: macOS, Linux, or Windows (with XAMPP or similar stack)
- **RAM**: Minimum 8GB (recommended 16GB for ML model operations)
- **Disk Space**: 5GB+ (includes ML models)

### Required Software

1. **XAMPP** (Apache + MySQL + PHP)
   - Download: https://www.apachefriends.org
   - Version: 8.0+ recommended
   - Components needed: Apache, MySQL, PHP

2. **Python 3.12**
   - Download: https://www.python.org/downloads/
   - Ensure pip is installed

3. **Git** (for version control)
   - Download: https://git-scm.com

### Recommended Tools
- VS Code or IDE of your choice
- MySQL Workbench (for database management)
- Postman (for API testing)

## 🚀 Installation Guide

### Local Docker setup (shared MySQL)

The local Compose stack uses the existing `shared-mysql` service on the external
`shared-db` network. It does not create or manage another database container.

- Website: `http://localhost:8083`
- Login: `http://localhost:8083/components/login.php`
- AI API: `http://localhost:5001/health`

Put the app-specific MySQL credentials in `.docker.env` (gitignored):

```dotenv
DB_USER=equiaid_local
DB_PASS=your-local-app-password
```

The MySQL user needs access to `brgy_equiaid.*`. Import
`database/brgy_equiaid.sql` only when initializing an empty database; it includes
seed records. The initial local setup has already imported this file.

```bash
docker compose stop predict
docker compose build
docker compose run --rm --no-deps predict python flood_scene.py --download
docker compose up -d
docker compose ps
docker compose logs --tail=50 predict
docker compose stop
```

Source files and model weights are mounted from this checkout. PHP/JavaScript
edits apply immediately; restart `predict` after Python changes. The first AI
build downloads TensorFlow and YOLO dependencies and can take several minutes.
Both published ports bind only to localhost. The web container uses
`PREDICT_API_URL=http://predict:5001` for server-side AI requests, while browser
requests use `http://localhost:5001`.

### Step 1: Install XAMPP

1. Download and install XAMPP from https://www.apachefriends.org
2. Install to your preferred location
3. Launch XAMPP Control Panel
4. Start **Apache** and **MySQL** services

Verify installation:
```bash
# Check if MySQL is running
mysql -u root

# Should enter MySQL shell without password
```

### Step 2: Set Up the Project

1. **Clone/Place Project** in XAMPP htdocs directory:
   ```bash
   /Applications/XAMPP/xamppfiles/htdocs/BrgyEquiaidSystem
   ```

2. **Create Database** (BrgyEquiaidSystem):
   ```bash
   # Access MySQL
   mysql -u root -p
   
   # In MySQL shell, execute:
   CREATE DATABASE brgy_equiaid;
   CREATE DATABASE brgy_equiaid CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   EXIT;
   ```

3. **Import Database Schema** (if available):
   ```bash
   mysql -u root brgy_equiaid < database/schema.sql
   ```
   
   > **Note**: If `schema.sql` is not available, contact the database administrator for the database structure.

### Step 3: Install Python Dependencies

1. **Create Virtual Environment**:
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/BrgyEquiaidSystem
   python3 -m venv venv
   ```

2. **Activate Virtual Environment**:
   ```bash
   # macOS/Linux
   source venv/bin/activate
   
   # Windows (Command Prompt)
   venv\Scripts\activate
   
   # Windows (PowerShell)
   venv\Scripts\Activate.ps1
   ```

3. **Install Required Packages**:
   ```bash
   pip install --upgrade pip
   pip install -r requirements.txt
   python flood_scene.py --download
   ```

   The download command prepares the additional local scene classifier. It is
   required once on each machine before flood prediction; runtime requests use
   the cached model and do not send uploaded images to an external service.

   **Key Python Packages**:
   - Flask: Web framework for API
   - TensorFlow & Keras: ML model framework
   - OpenCV: Image processing
   - Ultralytics: YOLO model implementation
   - NumPy: Numerical computations
   - Pandas: Data manipulation

### Step 4: Verify ML Models

Check that all pre-trained models are in place:

```
models/
├── flood_models/
│   ├── cnn_mobilenetv2.keras      (CNN model for flood detection)
│   ├── cnn_resnet50.keras         (CNN model for flood detection)
│   ├── yolo_nano_flood.onnx       (YOLO Nano model)
│   ├── yolo_small_flood.onnx      (YOLO Small model)
│   └── flood.yaml
├── damage_models/
│   ├── building_damage_model.keras
│   ├── damage_weights.weights.h5  (Building damage classification model)
│   └── class_labels.json          (Damage severity labels)
```

> **Note**: These are pre-trained models. If missing, they need to be generated or provided separately.

**Important: Download `cnn_resnet50.keras` from Google Drive**

1. Open this Google Drive folder:
   - https://drive.google.com/drive/u/0/folders/1oNMKVStQWfW-2rBt44vHSJ9Xy5N-Qxwo

2. Download the file named `cnn_resnet50.keras`.

3. Move the downloaded file into this folder:
   - `models/flood_models/`

4. Example command (macOS/Linux):
   ```bash
   mv ~/Downloads/cnn_resnet50.keras /Applications/XAMPP/xamppfiles/htdocs/BrgyEquiaidSystem/models/flood_models/
   ```

5. Verify the file exists in the correct path:
   ```bash
   ls -la /Applications/XAMPP/xamppfiles/htdocs/BrgyEquiaidSystem/models/flood_models/
   ```

Without `cnn_resnet50.keras`, the flood prediction endpoint will not work correctly.

## ⚙️ Configuration

### 1. Database Configuration

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'brgy_equiaid');
define('DB_USER', 'root');
define('DB_PASS', '');  // Change if MySQL password is set
define('DB_CHARSET', 'utf8mb4');
```

### 2. Environment Variables (Optional)

Create a `.env` file in the project root:

```env
DB_HOST=localhost
DB_NAME=brgy_equiaid
DB_USER=root
DB_PASS=
FLASK_ENV=development
FLASK_DEBUG=True
API_PORT=5000
```

### 3. Uploads Directory

Ensure the `uploads/` directory has proper permissions:

```bash
chmod -R 755 uploads/
chmod -R 755 logs/
```

## 🏃 Running the System

### 1. Start XAMPP Services

**Using XAMPP Control Panel**:
- Open XAMPP Control Panel
- Click **Start** next to Apache
- Click **Start** next to MySQL

Or via terminal:
```bash
# macOS
sudo /Applications/XAMPP/xamppfiles/apache2/bin/apachectl start
sudo /Applications/XAMPP/xamppfiles/bin/mysqld_safe &

# Stop services
sudo /Applications/XAMPP/xamppfiles/apache2/bin/apachectl stop
```

### 2. Start Python Flask API

In a separate terminal:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/BrgyEquiaidSystem

# Activate virtual environment
source venv/bin/activate

# Run Flask API
python predict_api.py
```

The Flask API should start on `http://localhost:5000`

### 3. Access the Web Application

Open your browser and navigate to:

```
## 📁 Project Structure

│   ├── announcements.php   # Announcements
│   ├── assistance.php      # Aid requests
│   └── street_status.php   # Street monitoring
│
├── admin/                   # Admin dashboard pages
│   ├── dashboard.php       # Admin overview
│   ├── user-management.php # User management
│   ├── resource-allocation.php
│   ├── prediction_analytics.php
│   ├── welfare-action.php
│   ├── activity-logs.php
│
├── backend/                 # Backend PHP logic
│   ├── auth.php            # Authentication
│   ├── home.php            # Home logic
│   ├── report.php          # Report handling
│   ├── announcements.php   # Announcements logic
│   ├── assistance.php      # Assistance requests
│   ├── street_monitoring.php
│   ├── disaster_map.php
│   ├── prediction_analytics.php
│   ├── my_profile.php
│   ├── my_reports.php
│   └── admin_*.php         # Admin backend files
│
├── js/                      # JavaScript files
│   └── *.js                # Frontend scripts and event handlers
│
├── styles/                  # CSS stylesheets
│   ├── global.css          # Global styles
│   ├── auth.css
│   ├── navbar.css
│   ├── home.css
│   ├── admin_*.css         # Admin styles
│   └── *.css               # Other component styles
│
├── models/                  # Pre-trained ML models
│   ├── flood_models/       # Flood detection models
│   └── damage_models/      # Building damage models
│
├── assets/                  # Images and media
├── uploads/                # User-uploaded files (reports, etc.)
├── logs/                   # Application logs
├── database/               # Database schemas (if available)
└── venv/                   # Python virtual environment
```

## ✨ Features

### For Regular Users
- ✅ Register and login
- ✅ View disaster announcements
- ✅ Request assistance/aid
- ✅ Submit disaster reports
- ✅ View real-time disaster map
- ✅ Monitor street status
- ✅ Manage profile

### For Administrators
- ✅ User management
- ✅ View activity logs
- ✅ Resource allocation
- ✅ Analytics and predictions
- ✅ Typhoon impact assessment
- ✅ Welfare action tracking
- ✅ Resident reports management
- ✅ Street monitoring

### AI & Prediction Features
- ✅ Flood detection using CNN models (MobileNetV2, ResNet50)
- ✅ Building damage classification (Severe/Moderate/Light)
- ✅ YOLO-based object segmentation
- ✅ Real-time image analysis
- ✅ Prediction analytics dashboard

## 🔗 API Endpoints

The Flask API provides endpoints for ML model predictions:

```
- Input: Base64-encoded image
- Output: Damage severity classification

GET http://localhost:5000/health
- Check API status
```

## 🔧 Troubleshooting

### Flood false positives and inference validation

The CNN segmentation models were trained to mark water pixels; their coverage
alone cannot reliably distinguish a flooded scene from logos, clothing, or paper.
`flood_scene.py` adds a separate, local
[CLIP scene check](https://huggingface.co/openai/clip-vit-base-patch32) before
returning any water estimate. It compares fixed flood-scene descriptions with
logos, gatherings, protests, dry streets, normal bodies of water, pools, documents,
and ordinary rain. A non-flood winner with a cosine-similarity margin of at least
0.02 returns `status: non_flood`, zero coverage, and an unannotated image. A weak
non-flood winner/tie is inconclusive. Flood-scene winners continue to the original
CNN estimates and optional YOLO confirmation. These are zero-shot scene decisions,
not calibrated probabilities or a guarantee of general accuracy.

On a machine without Docker, install and prepare it with:

```bash
pip install -r requirements.txt
python flood_scene.py --download
python predict_api.py
```

The original flood weights are unchanged. Preparation downloads the additional
official CLIP weights (about 600 MB), exports its vision encoder, caches the text
features, and quantizes the vision matrix weights. The API uses only the local
ONNX vision model (about 91 MB) and cached text vectors; it does not load the full
CLIP PyTorch/text model alongside TensorFlow. `scene_pixel_values` reproduces the
pinned processor's resize/crop/normalization, checked against the official processor.
The quantized model was checked on the same real-image controls after conversion.

All these artifacts live under `models/scene_models/`, which is gitignored.
Push `flood_scene.py`, `prepare_flood_scene.py`, and `requirements.txt` alongside
the prediction/validation/UI changes, then run the download command on the
destination machine; do not commit the model cache. This preparation command
reuses valid artifacts on subsequent runs. The model revision is pinned in the
module. Missing dependencies/weights return an explicit 503 setup error rather
than silently enabling unverified flood estimates again.

`/predict/flood` uses both exported YOLO segmentation models at their native
320×320 input size and both available 224×224 CNN segmentation models. YOLO
detections must be labeled `Flood Water` (or `Flood`/`Floodwater`) and score at
least 0.50. Their masks must overlap with an intersection-over-union of at least
0.30. At least one CNN must mark 30% of this shared region as water at a 0.50 pixel
threshold. Coverage is measured on the shared detector mask, with a 5% image-area
floor to discard small object detections (which can include clothing). Flooding
occupying less than this area also remains inconclusive. This floor is an
inference default, not a validated classifier for water versus clothing.
A disagreeing auxiliary CNN cannot erase the
detector mask. Coverage alone never counts as confidence. Color/position rules
are not used as definitive flood vetoes.

For scenes accepted by the scene check, original mean CNN coverage at the 0.50
pixel threshold is preserved in `cnn_flood_pct`. YOLO is additional confirmation,
not a prerequisite for returning a CNN estimate. If confirmed detector agreement is absent and the CNN
estimate reaches the original 12% coverage threshold, the API returns
`status: candidate`, a weighted CNN overlay, and zero *confirmed* coverage and
confidence. The interface shows “Possible Flooding — Review Needed” and the
estimated coverage, with report type/severity available for manual review.
This preserves flood candidates that were previously hidden by the YOLO gate.
The scene check now rejects the supplied logo, gathering, and protest controls
before calculating coverage, while retaining the supplied grey/muddy flood scenes.
Additional independent evaluation is still needed for new types of scenes.

Below the CNN estimate threshold, the status is `uncertain` and the interface
shows “Inconclusive.” Both review states retain the legacy `severity: None` and
`false_positive: true` fields; these fields do not prove the scene is non-flood.
Confirmed predictions return `status: confirmed`. Confidence is the minimum of
the strongest CNN mean score in the shared region and accepted detector scores;
it is not a calibrated probability. A missing/failing YOLO dependency cannot
disable an otherwise working CNN estimate.

The initial all-model intersection rule at 0.70 rejected genuine floods. In the
November 11, 2026 local audit, a muddy-water rescue photo produced YOLO scores of
0.798 and 0.653; the 0.70 cutoff suppressed the second detection. A grey-water
photo produced no YOLO detections even at 0.25. CNN agreement alone also marked
a labeled dry aerial scene as water, so it is not a reliable automatic fallback.
The [original training notebook](https://colab.research.google.com/drive/1dQj9t5h3wgai5d2pd17UawLn30NOoL8u)
confirms that the existing models were trained with 224×224 RGB/255 inputs.
The API retains this contract; changing to ImageNet normalization only during
inference would not match that training pipeline. Notebook logs show 232 training
and 58 validation records, zero YOLO background images, and validation mask recall
around 0.44–0.46 in the shown best-model runs. These are limitations of the shown
training/evaluation data, not evidence that the CNNs never detect floodwater.

Model weights have not been retrained. Restoring the CNN estimates fixes the
regression that hid their outputs; it does not solve their false positives.
Reliable separation of real floodwater from logos, signs, and clothing still
needs representative labeled flood/non-flood evaluation data.
Evaluate original non-flood photos (including logos, indoor gatherings, clothing,
and signs) alongside labeled flood photos before tuning thresholds or deploying.
Synthetic regression tests check decision logic, not the trained models' accuracy:

```bash
python -m unittest discover -s tests -p 'test_flood*.py' -v
```

**Solution**:
1. Ensure MySQL is running: `mysql -u root -p`
2. Check database name in [backend/db.php](backend/db.php)
3. Verify database exists: `SHOW DATABASES;`

### Issue: "ModuleNotFoundError" in Python
**Solution**:
1. Ensure virtual environment is activated: `source venv/bin/activate`
2. Install missing package: `pip install <package_name>`
3. Check Python version: `python --version` (should be 3.12+)

### Windows: certificate verification fails while downloading the scene model

If `python flood_scene.py --download` fails with `CERTIFICATE_VERIFY_FAILED` or
`unable to get local issuer certificate`, run these commands in the active venv:

```powershell
python -m pip install --upgrade pip certifi
python -m pip install "pip-system-certs>=5.3,<6"
python flood_scene.py --download
python predict_api.py
```

`pip-system-certs` makes Python use the Windows certificate store while retaining
TLS verification. It activates when the next Python process starts and is now
included in `requirements.txt` for Windows installations. If verification still
fails, check that the correct trusted root certificate for the network or HTTPS
inspection software is installed in Windows. The issuing organization should
provide that certificate; do not substitute an unverified certificate bundle.

If a trusted machine has already prepared this exact scene model, an offline
transfer avoids the download entirely. Copy these three generated files from
its `models/scene_models/` directory into the same directory on Windows:
`clip-vision-int8.onnx`, `scene-text-features.npy`, and `scene-model.json`.
Include the upstream CLIP license when redistributing the model. The metadata
must match the pinned model revision and prompts in `flood_scene.py`.
Then run `python flood_scene.py` (without `--download`) to verify the local
artifacts, followed by `python predict_api.py`. No Hugging Face connection is
needed for these prepared scene artifacts during inference. This does not repair
the Windows trust store; it provides an offline path to run the application.

The prepared bundle is also available at
`model-packages/equiaid-scene-model-offline.zip` for Git-based distribution. After
pulling it, run these commands from the project root in the active Windows venv:

```powershell
Expand-Archive .\model-packages\equiaid-scene-model-offline.zip -DestinationPath .\models\scene_models -Force
python flood_scene.py
python predict_api.py
```

Commit the ZIP and its `.sha256` sidecar, not the extracted `models/scene_models/`
directory. The ZIP contains the three prepared runtime files and the CLIP license.

### Issue: Flask API not starting
**Solution**:
1. Check if port 5000 is available: `lsof -i :5000`
2. Kill any existing process: `kill -9 <PID>`
3. Ensure all dependencies are installed: `pip install -r requirements.txt`

### Issue: ML Models not loading
**Solution**:
1. Verify all model files exist in `models/` directory
2. Check file permissions: `ls -la models/`
3. Ensure sufficient disk space: `df -h`
4. Check TensorFlow/Keras installation: `python -c "import tensorflow"`

### Issue: PHP files showing raw code
**Solution**:
1. Ensure Apache is running through XAMPP
2. Verify project is in correct location: `/Applications/XAMPP/xamppfiles/htdocs/`
3. Check PHP is enabled: Apache should serve `.php` files
4. Try accessing via `localhost` instead of file path

### Issue: Uploads directory permission errors
**Solution**:
```bash
# Fix permissions
chmod -R 755 uploads/
chmod -R 755 logs/

# On macOS, if still failing:
sudo chmod -R 777 uploads/
sudo chmod -R 777 logs/
```

## 📞 Support & Maintenance

### Regular Maintenance Tasks
1. **Database Backups**: Regularly backup the `brgy_equiaid` database
2. **Log Rotation**: Archive old log files from `logs/` directory
3. **Model Updates**: Retrain ML models periodically with new data
4. **Security Updates**: Keep XAMPP, PHP, Python, and packages updated

### Backup Database
```bash
# Create backup
mysqldump -u root brgy_equiaid > backup_brgy_equiaid_$(date +%Y%m%d).sql

# Restore backup
mysql -u root brgy_equiaid < backup_brgy_equiaid_20260101.sql
```

## 📝 Notes

- Default MySQL password is empty (root user)
- Virtual environment must be activated before running Python scripts
- Both Apache and MySQL must be running for full functionality
- Python models are resource-intensive; ensure adequate RAM available
- All user uploads are stored in `uploads/` directory with proper organization by year/month

## 📄 License

[Add your license information here]

## 👥 Contributors

[Add team information here]

---

**Last Updated**: April 2026
**Python Version**: 3.12.1
**XAMPP Required**: 8.0+
