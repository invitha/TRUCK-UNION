@echo off
echo ========================================
echo    Getting SHA-1 Certificate
echo ========================================
echo.

echo Checking for debug keystore...
echo.

REM Get debug SHA-1 (for testing)
echo [1] DEBUG SHA-1 (For Testing):
echo ----------------------------------------
keytool -list -v -alias androiddebugkey -keystore "%USERPROFILE%\.android\debug.keystore" -storepass android 2>nul | findstr "SHA1:"

if %errorlevel% neq 0 (
    echo Debug keystore not found. This is normal if you haven't run the app yet.
    echo.
)

echo.
echo.

REM Get release SHA-1 (for production)
echo [2] RELEASE SHA-1 (For Production):
echo ----------------------------------------

if exist "android\app\abra-vendor-keystore.jks" (
    keytool -list -v -alias abra-vendor-key -keystore "android\app\abra-vendor-keystore.jks" -storepass abra@vendor2024 2>nul | findstr "SHA1:"
    
    if %errorlevel% neq 0 (
        echo Could not read release keystore. Password might be incorrect.
    )
) else (
    echo Release keystore not found at: android\app\abra-vendor-keystore.jks
    echo You need to generate it first.
)

echo.
echo.
echo ========================================
echo INSTRUCTIONS:
echo ========================================
echo 1. Copy the SHA1 value (the long string after "SHA1:")
echo 2. Go to Firebase Console
echo 3. Project Settings -^> Your apps -^> Abra Vendor Android
echo 4. Click "Add fingerprint"
echo 5. Paste the SHA1 value
echo 6. Click Save
echo.
echo NOTE: Use DEBUG SHA-1 for testing
echo       Use RELEASE SHA-1 for production
echo ========================================
echo.
pause
