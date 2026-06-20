# Abra Vendor App - Setup Instructions

## Package Information
- **Package Name:** `com.abraglobal.vendor`
- **App Name:** Abra Vendor
- **Firebase Project:** abra-customer-and-vendor (shared with customer app)

---

## Step 1: Firebase Console Setup

### Add New Android App to Existing Firebase Project

1. **Go to Firebase Console:**
   ```
   https://console.firebase.google.com/project/abra-customer-and-vendor
   ```

2. **Add Android App:**
   - Click "Add app" button
   - Select Android icon
   - **Android package name:** `com.abraglobal.vendor`
   - **App nickname:** Abra Vendor
   - Click "Register app"

3. **Add SHA-1 Certificate:**
   - Get SHA-1 from customer app or generate new one:
   ```bash
   cd vendor_app/android
   keytool -list -v -keystore app/abra-vendor-keystore.jks -alias abra-vendor-key
   ```
   - Copy SHA-1 fingerprint
   - Paste in Firebase Console

4. **Download google-services.json:**
   - Download the file from Firebase Console
   - Place it in: `vendor_app/android/app/google-services.json`

5. **Update firebase_options.dart:**
   - Open the downloaded `google-services.json`
   - Find the `client` array → `client_info` → `mobilesdk_app_id`
   - Copy the App ID (format: `1:49227638741:android:XXXXX`)
   - Update `vendor_app/lib/firebase_options.dart`:
   ```dart
   static const FirebaseOptions android = FirebaseOptions(
     apiKey: 'AIzaSyAdYUazegjDac9w4xZYFJvUEQHsD74wYLo',
     appId: '1:49227638741:android:YOUR_VENDOR_APP_ID_HERE', // Replace this
     messagingSenderId: '49227638741',
     projectId: 'abra-customer-and-vendor',
     storageBucket: 'abra-customer-and-vendor.firebasestorage.app',
   );
   ```

---

## Step 2: Generate Keystore (If Not Exists)

```bash
cd vendor_app/android/app
keytool -genkey -v -keystore abra-vendor-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias abra-vendor-key

# Use these credentials:
# Password: abra@vendor2024
# Alias: abra-vendor-key
```

---

## Step 3: Enable Firebase Services

In Firebase Console, enable:

1. **Authentication:**
   - Email/Password
   - Google Sign-In
   - Add SHA-1 certificate

2. **Cloud Messaging (FCM):**
   - Already enabled by default

3. **Firestore/Realtime Database:**
   - If needed for real-time features

---

## Step 4: Database Setup

### Create Vendor Tables in MySQL

Connect to your database and run:

```sql
-- Vendor KYC Table
CREATE TABLE IF NOT EXISTS vendor_kyc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firebase_uid VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    company_name VARCHAR(255),
    gst_number VARCHAR(50),
    pan_number VARCHAR(50),
    aadhaar_number VARCHAR(50),
    address TEXT,
    documents JSON,
    kyc_status ENUM('pending', 'submitted', 'verified', 'rejected') DEFAULT 'pending',
    submitted_at TIMESTAMP NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_firebase_uid (firebase_uid),
    INDEX idx_kyc_status (kyc_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vendor Vehicles Table
CREATE TABLE IF NOT EXISTS vendor_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firebase_uid VARCHAR(255) NOT NULL,
    vehicle_number VARCHAR(50) NOT NULL,
    vehicle_type ENUM('truck', 'mini_truck', 'tempo', 'van', 'container', 'trailer') NOT NULL,
    capacity VARCHAR(50),
    driver_name VARCHAR(255),
    driver_phone VARCHAR(20),
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_firebase_uid (firebase_uid),
    INDEX idx_vehicle_number (vehicle_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update app_users table to support vendors
ALTER TABLE app_users 
MODIFY COLUMN role ENUM('customer', 'vendor', 'admin') DEFAULT 'customer';
```

---

## Step 5: Install Dependencies

```bash
cd vendor_app
flutter pub get
```

---

## Step 6: Run the App

### Debug Mode:
```bash
flutter run
```

### Release Mode:
```bash
flutter build apk --release
# APK will be in: build/app/outputs/flutter-apk/app-release.apk
```

### Install on Device:
```bash
flutter install
```

---

## Step 7: Test Login

1. **Create Test Vendor Account:**
   - Open app
   - Click "Sign Up"
   - Enter details
   - Sign up

2. **Verify in Database:**
   ```sql
   SELECT * FROM app_users WHERE role = 'vendor';
   ```

3. **Test Google Sign-In:**
   - Make sure SHA-1 is added to Firebase
   - Try Google Sign-In

---

## Package Structure

```
com.abraglobal.vendor
├── Customer App: com.abraglobal.shipping
└── Vendor App:   com.abraglobal.vendor
```

Both apps share:
- Same Firebase project
- Same database
- Same authentication
- Different package names
- Different features

---

## Important Notes

1. **SHA-1 Certificate:**
   - Must be added to Firebase Console
   - Required for Google Sign-In
   - Get from keystore or debug keystore

2. **Package Name:**
   - Customer: `com.abraglobal.shipping`
   - Vendor: `com.abraglobal.vendor`
   - Must be different for separate apps

3. **Database:**
   - Both apps use same `app_users` table
   - Role field differentiates: `customer` vs `vendor`

4. **API Endpoints:**
   - Create vendor-specific endpoints in `api1/vendor/`
   - Reuse common endpoints from `api1/`

---

## Next Steps

After setup is complete:
1. ✅ Test login/signup
2. ✅ Test Google Sign-In
3. ✅ Build vendor dashboard
4. ✅ Implement KYC verification
5. ✅ Add vehicle management
6. ✅ Test notifications

---

## Troubleshooting

### Google Sign-In Fails:
- Check SHA-1 is added to Firebase
- Verify package name matches
- Download latest google-services.json

### Build Fails:
- Run `flutter clean`
- Run `flutter pub get`
- Check Android SDK is installed

### Database Connection Fails:
- Check API endpoints are accessible
- Verify database credentials
- Test with Postman first

---

## Support

For issues, contact: support@abra-logistic.com
