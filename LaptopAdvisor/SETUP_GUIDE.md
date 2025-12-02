# LaptopAdvisor - Complete Setup Guide

## 📋 Table of Contents
1. [Prerequisites](#prerequisites)
2. [XAMPP Setup](#xampp-setup)
3. [Database Setup](#database-setup)
4. [Python Setup (ML Recommendation Engine)](#python-setup)
5. [Running the Application](#running-the-application)
6. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Software

- **XAMPP** (Apache + MySQL + PHP)
  - Download from: https://www.apachefriends.org/
  - Version: Latest (PHP 7.4+ recommended)
  
- **Python** 3.8 or higher
  - Download from: https://www.python.org/downloads/
  - ⚠️ **IMPORTANT**: During installation, check "Add Python to PATH"

- **Web Browser** (Chrome, Firefox, or Edge)

---

## XAMPP Setup

### Step 1: Install XAMPP

1. Download XAMPP from https://www.apachefriends.org/
2. Run the installer
3. Select components: Apache, MySQL, PHP, phpMyAdmin
4. Install to default location: `C:\xampp`
5. Complete installation

### Step 2: Start XAMPP Services

1. Open **XAMPP Control Panel** (search for "XAMPP" in Start Menu)
2. Click **Start** for:
   - ✅ Apache
   - ✅ MySQL
3. Wait for both to turn green

### Step 3: Verify XAMPP is Running

- Open browser and go to: `http://localhost`
- You should see the XAMPP dashboard
- Click **phpMyAdmin** to verify MySQL is accessible

---

## Database Setup

### Step 1: Create Database

1. Open **phpMyAdmin**: http://localhost/phpmyadmin
2. Click **"New"** in left sidebar
3. Database name: `laptop_advisor_db`
4. Collation: `utf8mb4_general_ci`
5. Click **Create**

### Step 2: Import Database Schema

**Option A: Using phpMyAdmin (Recommended)**

1. Click on `laptop_advisor_db` in left sidebar
2. Go to **Import** tab
3. Click **Choose File**
4. Navigate to: `c:\xampp\htdocs\LaptopAdvisor\laptop_advisor_db (1).sql`
5. Click **Import** at bottom
6. Wait for "Import has been successfully finished" message

**Option B: Using Command Line**

```bash
cd C:\xampp\mysql\bin
mysql -u root -p laptop_advisor_db < "C:\xampp\htdocs\LaptopAdvisor\laptop_advisor_db (1).sql"
```
(Press Enter when asked for password - default XAMPP has no password)

### Step 3: Run Database Migrations

Apply the address management migrations:

1. In phpMyAdmin, select `laptop_advisor_db`
2. Click **SQL** tab
3. Copy and paste contents of:
   - `add_user_default_address_migration.sql`
   - `add_shipping_address_migration.sql`
4. Click **Go** for each

### Step 4: Verify Database

Check that these tables exist:
- ✅ `users`
- ✅ `products`
- ✅ `orders`
- ✅ `order_items`
- ✅ `ratings`
- ✅ `coupons`

### Step 5: Create Database Connection File

Ensure `includes/db.php` exists with correct credentials:

```php
<?php
$host = 'localhost';
$db_name = 'laptop_advisor_db';
$username = 'root';
$password = ''; // Default XAMPP has empty password

$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
```

---

## Python Setup

### Step 1: Verify Python Installation

Open **Command Prompt** and run:

```bash
python --version
```

Expected output: `Python 3.8.x` or higher

If this fails:
- Reinstall Python with "Add to PATH" checked
- Restart Command Prompt

### Step 2: Install Python Dependencies

```bash
cd C:\xampp\htdocs\LaptopAdvisor\recommendation_engine
pip install -r requirements.txt
```

This installs:
- Flask (API server)
- scikit-learn (ML algorithms)
- pandas (data processing)
- numpy (numerical computing)
- mysql-connector-python (database access)

**Installation may take 2-5 minutes**

### Step 3: Configure Database Connection

1. Navigate to: `C:\xampp\htdocs\LaptopAdvisor\recommendation_engine`
2. Copy `.env.example` to `.env`:
   ```bash
   copy .env.example .env
   ```
3. Open `.env` in Notepad and verify:
   ```
   DB_HOST=localhost
   DB_NAME=laptop_advisor_db
   DB_USER=root
   DB_PASSWORD=
   DB_PORT=3306
   ```

### Step 4: Train Initial ML Model

```bash
cd C:\xampp\htdocs\LaptopAdvisor\recommendation_engine
python recommender.py
```

Expected output:
- "Loading data from database..."
- "Training model..."
- "Model saved successfully!"

**Note**: This may show warnings about insufficient data if you have a fresh database. Add some test ratings first.

---

## Running the Application

### Method 1: Manual Start (for testing)

**Terminal 1 - Python API Server:**
```bash
cd C:\xampp\htdocs\LaptopAdvisor\recommendation_engine
python api.py
```

Keep this terminal open. You should see:
```
 * Running on http://127.0.0.1:5000
```

**Terminal 2 - Test the Website:**

Open browser: `http://localhost/LaptopAdvisor`

### Method 2: Using Batch File (Recommended)

Double-click: `C:\xampp\htdocs\LaptopAdvisor\recommendation_engine\start_api.bat`

This starts the Python API in the background.

### Method 3: Both at Once

1. Start XAMPP Control Panel
2. Start Apache and MySQL
3. Run `start_api.bat`
4. Open browser to `http://localhost/LaptopAdvisor`

---

## Application Features & URLs

| Feature | URL |
|---------|-----|
| Homepage | `http://localhost/LaptopAdvisor/` |
| Products | `http://localhost/LaptopAdvisor/products.php` |
| Login/Register | `http://localhost/LaptopAdvisor/login.php` |
| Cart | `http://localhost/LaptopAdvisor/cart.php` |
| Checkout | `http://localhost/LaptopAdvisor/checkout.php` |
| Profile | `http://localhost/LaptopAdvisor/profile.php` |
| Edit Profile | `http://localhost/LaptopAdvisor/edit_profile.php` |
| phpMyAdmin | `http://localhost/phpmyadmin` |
| Python API Health | `http://127.0.0.1:5000/api/health` |

---

## Troubleshooting

### XAMPP Issues

**Port 80 Already in Use (Apache won't start)**

Apache port conflict with other services:

1. Open XAMPP Control Panel
2. Click **Config** next to Apache
3. Select **httpd.conf**
4. Find `Listen 80` and change to `Listen 8080`
5. Save and restart Apache
6. Access site at: `http://localhost:8080/LaptopAdvisor`

**Port 3306 Already in Use (MySQL won't start)**

Another MySQL instance is running:

1. Open Task Manager
2. End any `mysqld.exe` processes
3. Try starting MySQL again in XAMPP

**Error: "Access forbidden! You don't have permission to access..."**

1. Check that `LaptopAdvisor` folder is in `C:\xampp\htdocs\`
2. Verify Apache is running (green in XAMPP Control Panel)

### Database Issues

**"Connection failed" Error**

Check `includes/db.php` credentials match phpMyAdmin:
- Default username: `root`
- Default password: (empty)
- Database: `laptop_advisor_db`

**Tables Don't Exist**

Re-import the SQL file:
1. Drop database in phpMyAdmin
2. Create new `laptop_advisor_db`
3. Import `laptop_advisor_db (1).sql` again

**Migration Errors**

If migrations fail:
- Check if columns already exist
- Run migrations individually
- Check phpMyAdmin error messages

### Python/ML Issues

**"python is not recognized" Error**

Python not in PATH:

1. Find Python installation: `C:\Users\YourName\AppData\Local\Programs\Python\Python3x\`
2. Add to PATH or reinstall Python with "Add to PATH" checked

**"No module named 'flask'" Error**

Dependencies not installed:

```bash
cd C:\xampp\htdocs\LaptopAdvisor\recommendation_engine
pip install -r requirements.txt
```

**API Port 5000 Already in Use**

Another service using port 5000:

1. Open `api.py`
2. Change `app.run(port=5000)` to `app.run(port=5001)`
3. Update PHP API calls in `includes/recommendation_api.php` to use port 5001

**No Recommendations Returned**

Insufficient data:

1. Add test users and products
2. Create ratings in database
3. Retrain model: `python recommender.py`

**Database Connection Failed (Python)**

Check `.env` file:
- Ensure no spaces around `=`
- Verify database name matches phpMyAdmin
- Test with: `python -c "import mysql.connector; print('OK')"`

### Browser Issues

**Page Shows PHP Code Instead of Rendering**

Apache not processing PHP:

1. Ensure Apache is started in XAMPP
2. Access via `http://localhost/...` not `file:///...`
3. Files must have `.php` extension

**Session Errors**

Clear browser cookies or use incognito mode

**Images Not Loading**

Check `uploads/` folder exists and has permissions

---

## Quick Start Checklist

Use this checklist for a fresh installation:

- [ ] Install XAMPP
- [ ] Start Apache + MySQL
- [ ] Create `laptop_advisor_db` database
- [ ] Import `laptop_advisor_db (1).sql`
- [ ] Run address migrations
- [ ] Install Python 3.8+
- [ ] Install Python packages: `pip install -r requirements.txt`
- [ ] Configure `.env` file
- [ ] Train ML model: `python recommender.py`
- [ ] Start Python API: `python api.py` or `start_api.bat`
- [ ] Open browser: `http://localhost/LaptopAdvisor`
- [ ] Register test account
- [ ] Test ML recommendations on products page

---

## Maintenance

### Daily Operations

**Starting the System:**
1. Open XAMPP Control Panel
2. Start Apache and MySQL
3. Run `start_api.bat` (for ML recommendations)

**Stopping the System:**
1. Close Python API terminal
2. Stop Apache and MySQL in XAMPP

### Periodic Tasks

**Retrain ML Model (Weekly/Monthly):**
```bash
cd C:\xampp\htdocs\LaptopAdvisor\recommendation_engine
python recommender.py
```

**Backup Database (Important!):**
1. phpMyAdmin → Export → Go
2. Save `.sql` file with date: `laptop_advisor_backup_2025-11-25.sql`

**Clear Caches:**
PHP cache: Restart Apache in XAMPP
Python cache: Restart API server

---

## Support & Resources

- **Python API Documentation**: `recommendation_engine/README.md`
- **ML Engine Setup**: `recommendation_engine/SETUP_GUIDE.md`
- **XAMPP Documentation**: https://www.apachefriends.org/docs/
- **PHP Documentation**: https://www.php.net/manual/
- **Flask Documentation**: https://flask.palletsprojects.com/

---

## Security Notes

⚠️ **This is a development setup. For production:**

1. Change MySQL root password
2. Update `includes/db.php` with new credentials
3. Update `.env` with new credentials
4. Enable HTTPS
5. Use environment-specific configuration
6. Implement proper error logging
7. Secure file upload directory
8. Add rate limiting to API

---

*Last updated: November 2025*
