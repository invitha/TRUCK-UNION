@echo off
echo ========================================
echo    Generate Release Keystore
echo ========================================
echo.

REM Check if keystore already exists
if exist "android\app\abra-vendor-keystore.jks" (
    echo Keystore already exists at: android\app\abra-vendor-keystore.jks
    echo.
    choice /C YN /M "Do you want to overwrite it"
    if errorlevel 2 goto :end
    echo.
)

REM Create android/app directory if it doesn't exist
if not exist "android\app" (
    mkdir "android\app"
)

echo Generating keystore...
echo.
echo Please answer the following questions:
echo (You can press Enter to skip optional fields)
echo.

keytool -genkey -v -keystore "android\app\abra-vendor-keystore.jks" -keyalg RSA -keysize 2048 -validity 10000 -alias abra-vendor-key -storepass abra@vendor2024 -keypass abra@vendor2024

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo SUCCESS!
    echo ========================================
    echo Keystore created at: android\app\abra-vendor-keystore.jks
    echo.
    echo Keystore Details:
    echo - File: android\app\abra-vendor-keystore.jks
    echo - Alias: abra-vendor-key
    echo - Password: abra@vendor2024
    echo.
    echo Now run: get_sha1.bat to get your SHA-1 certificate
    echo ========================================
) else (
    echo.
    echo ERROR: Failed to generate keystore
    echo Please make sure Java JDK is installed
)

:end
echo.
pause
