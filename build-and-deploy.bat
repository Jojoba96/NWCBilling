@echo off
REM NWC Customer Dashboard Build and Deploy Script

echo.
echo ========================================
echo NWC Customer Dashboard Setup
echo ========================================
echo.

REM Check if Node.js is installed
node --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Node.js is not installed!
    echo Please install Node.js from https://nodejs.org/
    pause
    exit /b 1
)

echo Step 1: Navigate to frontend folder...
cd /d "%~dp0frontend"

echo.
echo Step 2: Installing dependencies...
call npm install

echo.
echo Step 3: Building React app...
call npm run build

echo.
echo Step 4: Checking build output...
if exist "dist" (
    echo SUCCESS: React app built successfully!
    echo.
    echo ========================================
    echo DEPLOYMENT READY
    echo ========================================
    echo.
    echo You can now access the Customer Dashboard at:
    echo http://localhost/NWCBilling/customer-dashboard.php
    echo.
    echo Make sure XAMPP is running!
    echo.
) else (
    echo ERROR: Build failed!
    echo Please check the error messages above.
    pause
    exit /b 1
)

echo.
pause
