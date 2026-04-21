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
   pip install flask flask-cors numpy opencv-python tensorflow keras ultralytics pandas
   ```

   Or if a `requirements.txt` exists:
   ```bash
   pip install -r requirements.txt
   ```

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

## ⚙️ Configuration

### 1. Database Configuration

Edit [backend/db.php](backend/db.php):

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
http://localhost/BrgyEquiaidSystem/
```

**Default Credentials** (if using sample data):
- Contact system administrator for initial credentials
- First-time setup may require creating an admin account

## 📁 Project Structure

```
BrgyEquiaidSystem/
├── index.php                 # Main entry point (redirects to components)
├── predict_api.py           # Flask API for ML predictions
│
├── components/              # Frontend pages
│   ├── index.php           # Home page
│   ├── login.php           # Login page
│   ├── announcements.php   # Announcements
│   ├── assistance.php      # Aid requests
│   ├── disaster_map.php    # Real-time disaster map
│   ├── my_reports.php      # User's reports
│   ├── my_profile.php      # User profile
│   ├── navbar.php          # Navigation bar
│   ├── footer.php          # Footer
│   └── street_status.php   # Street monitoring
│
├── admin/                   # Admin dashboard pages
│   ├── dashboard.php       # Admin overview
│   ├── user-management.php # User management
│   ├── resource-allocation.php
│   ├── prediction_analytics.php
│   ├── street_monitoring.php
│   ├── typhoon-impact.php
│   ├── welfare-action.php
│   ├── activity-logs.php
│   ├── resident-reports.php
│   └── sidebar.php
│
├── backend/                 # Backend PHP logic
│   ├── db.php              # Database connection
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
POST http://localhost:5000/predict-flood
- Input: Base64-encoded image
- Output: Flood probability and predictions

POST http://localhost:5000/predict-damage
- Input: Base64-encoded image
- Output: Damage severity classification

GET http://localhost:5000/health
- Check API status
```

## 🔧 Troubleshooting

### Issue: "Cannot connect to database"
**Solution**:
1. Ensure MySQL is running: `mysql -u root -p`
2. Check database name in [backend/db.php](backend/db.php)
3. Verify database exists: `SHOW DATABASES;`

### Issue: "ModuleNotFoundError" in Python
**Solution**:
1. Ensure virtual environment is activated: `source venv/bin/activate`
2. Install missing package: `pip install <package_name>`
3. Check Python version: `python --version` (should be 3.12+)

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
