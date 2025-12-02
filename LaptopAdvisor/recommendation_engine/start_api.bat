@echo off
REM Startup script for LaptopAdvisor Recommendation Engine
REM This script starts the Flask API server

echo ========================================
echo LaptopAdvisor Recommendation Engine
echo ========================================
echo.

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python is not installed or not in PATH
    echo Please install Python 3.8 or higher
    pause
    exit /b 1
)

echo Python found!
echo.

REM Change to recommendation_engine directory
cd /d "%~dp0"

REM Check if virtual environment exists
if not exist "venv" (
    echo Creating virtual environment...
    python -m venv venv
    echo.
)

REM Activate virtual environment
echo Activating virtual environment...
call venv\Scripts\activate.bat

REM Check if requirements are installed
echo Checking dependencies...
pip show flask >nul 2>&1
if errorlevel 1 (
    echo Installing dependencies...
    pip install -r requirements.txt
    echo.
)

REM Check if .env file exists
if not exist ".env" (
    echo WARNING: .env file not found!
    echo Copying .env.example to .env...
    copy .env.example .env
    echo.
    echo Please edit .env file with your database credentials
    pause
)

REM Train model if not exists
if not exist "models\recommendation_model.pkl" (
    echo No trained model found. Training model...
    python recommender.py
    echo.
)

REM Start the Flask API server
echo Starting Flask API server...
echo.
python api.py

pause
