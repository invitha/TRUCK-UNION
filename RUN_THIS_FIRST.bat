@echo off
echo ========================================
echo INSTALLING LOCATION TRACKING PACKAGES
echo ========================================
echo.
echo This will install geolocator and geocoding packages...
echo.

cd /d "%~dp0"

echo Running: flutter pub get
flutter pub get

echo.
echo ========================================
echo DONE!
echo ========================================
echo.
echo Now you can run the app with: flutter run
echo.
pause
