# Complete Firebase Setup Guide - Android + iOS

## 📱 Overview

You need to add **TWO apps** to the same Firebase project:
1. **Android App** - Package: `com.abraglobal.vendor`
2. **iOS App** - Bundle ID: `com.abraglobal.vendor`

Both apps will share the same Firebase project: `abra-customer-and-vendor`

---

## 🤖 ANDROID SETUP

### Step 1: Add Android App to Firebase

1. **Open Firebase Console:**
   ```
   https://console.firebase.google.com/project/abra-customer-and-vendor
   ```

2. **Click "Add app" button → Select Android icon (robot)**

3. **Register Android App:**
   - **Android package name:** `com.abraglobal.vendor`
   - **App nickname (optional):** `Abra Vendor Android`
   - **Debug signing certificate SHA-1:** (Get it below)

4. **Get SHA-1 Certificate:**
   
   Open terminal and run:
   ```bash
   # For Debug builds (testing):
   keytool -list -v -alias androiddebugkey -keystore ~/.android/debug.keystore
   ```
   When prompted for password, enter: `android`
   
   ```bash
   # For Release builds (production):
   cd vendor_app/android
   keytool -list -v -keystore app/abra-vendor-keystore.jks -alias abra-vendor-key
   ```
   When prompted for password, enter: `abra@vendor2024`
   
   **Copy the SHA-1 fingerprint** (looks like: `AA:BB:CC:DD:EE:FF:...`)
   
   Paste it in Firebase Console

5. **Click "Register app"**

### Step 2: Download google-services.json

1. **Click "Download google-services.json"**
2. **Save the file**
3. **Place it in your project:**
   ```
   vendor_app/android/app/google-services.json
   ```
   
   ⚠️ **IMPORTANT:** Must be in the `app` folder!

### Step 3: Get Android App ID

1. **Open the downloaded `google-services.json` file**
2. **Find this section:**
   ```json
   "client": [
     {
       "client_info": {
         "mobilesdk_app_id": "1:49227638741:android:abc123def456",
         ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
         This is your Android App ID - Copy it!
       }
     }
   ]
   ```
3. **Copy the `mobilesdk_app_id` value**

### Step 4: Update firebase_options.dart (Android)

1. **Open:** `vendor_app/lib/firebase_options.dart`
2. **Find line ~47:**
   ```dart
   appId: '1:49227638741:android:PASTE_YOUR_ANDROID_APP_ID_HERE',
   ```
3. **Replace with your actual Android App ID:**
   ```dart
   appId: '1:49227638741:android:abc123def456', // Your actual ID
   ```

---

## 🍎 iOS SETUP

### Step 5: Add iOS App to Firebase

1. **In Firebase Console, click "Add app" again → Select iOS icon (Apple)**

2. **Register iOS App:**
   - **iOS bundle ID:** `com.abraglobal.vendor`
   - **App nickname (optional):** `Abra Vendor iOS`
   - **App Store ID (optional):** Leave empty for now
   - **Click "Register app"**

### Step 6: Download GoogleService-Info.plist

1. **Click "Download GoogleService-Info.plist"**
2. **Save the file**
3. **Place it in your project:**
   ```
   vendor_app/ios/Runner/GoogleService-Info.plist
   ```

### Step 7: Get iOS App ID and Client ID

1. **Open the downloaded `GoogleService-Info.plist` file**
2. **Find these values:**
   ```xml
   <key>GOOGLE_APP_ID</key>
   <string>1:49227638741:ios:xyz789abc123</string>
   ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
   This is your iOS App ID - Copy it!
   
   <key>CLIENT_ID</key>
   <string>49227638741-abcdefg123456.apps.googleusercontent.com</string>
   ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
   This is your iOS Client ID - Copy it!
   
   <key>REVERSED_CLIENT_ID</key>
   <string>com.googleusercontent.apps.49227638741-abcdefg123456</string>
   ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
   This is your Reversed Client ID - Copy it!
   ```

### Step 8: Update firebase_options.dart (iOS)

1. **Open:** `vendor_app/lib/firebase_options.dart`
2. **Find the iOS section (around line 57):**
   ```dart
   static const FirebaseOptions ios = FirebaseOptions(
     apiKey: 'AIzaSyAx2AfcgxIDB7k9Shq3y2paRZy7u3RXnQk',
     appId: '1:49227638741:ios:PASTE_YOUR_IOS_APP_ID_HERE',
     messagingSenderId: '49227638741',
     projectId: 'abra-customer-and-vendor',
     storageBucket: 'abra-customer-and-vendor.firebasestorage.app',
     iosClientId: '49227638741-PASTE_IOS_CLIENT_ID.apps.googleusercontent.com',
     authDomain: 'abra-customer-and-vendor.firebaseapp.com',
     iosBundleId: 'com.abraglobal.vendor',
   );
   ```

3. **Replace with your actual values:**
   ```dart
   static const FirebaseOptions ios = FirebaseOptions(
     apiKey: 'AIzaSyAx2AfcgxIDB7k9Shq3y2paRZy7u3RXnQk',
     appId: '1:49227638741:ios:xyz789abc123', // Your iOS App ID
     messagingSenderId: '49227638741',
     projectId: 'abra-customer-and-vendor',
     storageBucket: 'abra-customer-and-vendor.firebasestorage.app',
     iosClientId: '49227638741-abcdefg123456.apps.googleusercontent.com', // Your iOS Client ID
     authDomain: 'abra-customer-and-vendor.firebaseapp.com',
     iosBundleId: 'com.abraglobal.vendor',
   );
   ```

### Step 9: Update Info.plist (iOS)

1. **Open:** `vendor_app/ios/Runner/Info.plist`
2. **Find this section:**
   ```xml
   <key>CFBundleURLSchemes</key>
   <array>
       <string>com.googleusercontent.apps.49227638741-PASTE_YOUR_REVERSED_CLIENT_ID</string>
   </array>
   ```
3. **Replace with your REVERSED_CLIENT_ID:**
   ```xml
   <key>CFBundleURLSchemes</key>
   <array>
       <string>com.googleusercontent.apps.49227638741-abcdefg123456</string>
   </array>
   ```

---

## 🔐 ENABLE AUTHENTICATION

### Step 10: Enable Sign-In Methods

1. **In Firebase Console, go to:**
   - Left sidebar → **"Build"** → **"Authentication"**
   - Click **"Get started"** (if first time)

2. **Enable Email/Password:**
   - Click on "Email/Password"
   - Toggle **"Enable"**
   - Click **"Save"**

3. **Enable Google Sign-In:**
   - Click on "Google"
   - Toggle **"Enable"**
   - **Project support email:** Select your email from dropdown
   - Click **"Save"**

### Step 11: Add SHA-1 to Project Settings

1. **Go to Project Settings:**
   - Click gear icon (⚙️) next to "Project Overview"
   - Click "Project settings"

2. **Scroll down to "Your apps" section**

3. **Find "Abra Vendor Android" app**

4. **Click "Add fingerprint"**

5. **Paste your SHA-1 certificate** (from Step 4)

6. **Click "Save"**

---

## ✅ VERIFICATION

### Final File Structure

```
vendor_app/
├── android/
│   └── app/
│       └── google-services.json          ← Android config file
├── ios/
│   └── Runner/
│       ├── GoogleService-Info.plist      ← iOS config file
│       └── Info.plist                    ← Updated with REVERSED_CLIENT_ID
└── lib/
    └── firebase_options.dart             ← Updated with both App IDs
```

### Test Your Setup

1. **Clean and get dependencies:**
   ```bash
   cd vendor_app
   flutter clean
   flutter pub get
   ```

2. **Test Android:**
   ```bash
   flutter run -d android
   ```

3. **Test iOS:**
   ```bash
   flutter run -d ios
   ```

4. **Test Sign Up:**
   - Open app
   - Click "Sign Up"
   - Enter details
   - Should create account successfully

5. **Test Google Sign-In:**
   - Click "Continue with Google"
   - Select account
   - Should sign in successfully

---

## 📋 Quick Checklist

### Android Setup:
- [ ] Added Android app to Firebase Console
- [ ] Package name: `com.abraglobal.vendor`
- [ ] Added SHA-1 certificate
- [ ] Downloaded `google-services.json`
- [ ] Placed in `vendor_app/android/app/google-services.json`
- [ ] Updated `firebase_options.dart` with Android App ID

### iOS Setup:
- [ ] Added iOS app to Firebase Console
- [ ] Bundle ID: `com.abraglobal.vendor`
- [ ] Downloaded `GoogleService-Info.plist`
- [ ] Placed in `vendor_app/ios/Runner/GoogleService-Info.plist`
- [ ] Updated `firebase_options.dart` with iOS App ID and Client ID
- [ ] Updated `Info.plist` with REVERSED_CLIENT_ID

### Authentication:
- [ ] Enabled Email/Password authentication
- [ ] Enabled Google Sign-In
- [ ] Added SHA-1 to Project Settings

### Testing:
- [ ] App builds successfully
- [ ] Email signup works
- [ ] Google Sign-In works
- [ ] User appears in Firebase Authentication console

---

## 🐛 Troubleshooting

### "google-services.json not found"
- Verify file is in: `vendor_app/android/app/google-services.json`
- NOT in: `vendor_app/android/google-services.json`

### "GoogleService-Info.plist not found"
- Verify file is in: `vendor_app/ios/Runner/GoogleService-Info.plist`

### "Google Sign-In failed on Android"
- Make sure SHA-1 is added to Firebase Console
- Download fresh `google-services.json`
- Run: `flutter clean && flutter pub get`

### "Google Sign-In failed on iOS"
- Make sure REVERSED_CLIENT_ID is in `Info.plist`
- Verify Bundle ID matches: `com.abraglobal.vendor`

### "Package name mismatch"
- Check `android/app/build.gradle`: `applicationId "com.abraglobal.vendor"`
- Check Firebase Console package name matches exactly

---

## 🎉 Success!

Once all steps are complete:
1. Both Android and iOS apps are registered in Firebase
2. Authentication is enabled
3. App can sign in users on both platforms
4. Users are stored in same Firebase project

**Next:** Build the vendor dashboard and features! 🚀
