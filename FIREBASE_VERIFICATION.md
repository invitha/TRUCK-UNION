# Firebase Setup Verification Checklist

## ✅ Checklist - Complete These Steps:

### 1. Firebase Console Setup
- [ ] Opened Firebase Console: https://console.firebase.google.com/
- [ ] Selected project: "abra-customer-and-vendor"
- [ ] Added new Android app
- [ ] Package name: `com.abraglobal.vendor`
- [ ] App nickname: "Abra Vendor"

### 2. SHA-1 Certificate
- [ ] Generated SHA-1 fingerprint using keytool
- [ ] Added SHA-1 to Firebase Console
- [ ] SHA-1 format verified (AA:BB:CC:DD:...)

### 3. google-services.json
- [ ] Downloaded google-services.json from Firebase
- [ ] Placed file in: `vendor_app/android/app/google-services.json`
- [ ] File location verified (must be in `app` folder)

### 4. firebase_options.dart
- [ ] Opened google-services.json
- [ ] Found "mobilesdk_app_id" value
- [ ] Updated firebase_options.dart with correct App ID
- [ ] Saved the file

### 5. Authentication Methods
- [ ] Enabled Email/Password authentication
- [ ] Enabled Google Sign-In
- [ ] Added support email for Google Sign-In
- [ ] Added SHA-1 fingerprint in Project Settings

### 6. Cloud Messaging
- [ ] Verified Cloud Messaging is enabled
- [ ] FCM API is active

---

## 🧪 Test Your Setup

### Test 1: Build the App
```bash
cd vendor_app
flutter clean
flutter pub get
flutter build apk --debug
```

**Expected:** Build succeeds without Firebase errors

### Test 2: Run on Device
```bash
flutter run
```

**Expected:** App launches, shows splash screen, then login screen

### Test 3: Test Email Signup
1. Open app
2. Click "Sign Up"
3. Enter: Name, Email, Password
4. Click "Sign Up"

**Expected:** Account created, navigates to vendor dashboard

### Test 4: Test Google Sign-In
1. Open app
2. Click "Continue with Google"
3. Select Google account

**Expected:** Signs in successfully, navigates to vendor dashboard

### Test 5: Verify in Database
```sql
SELECT * FROM app_users WHERE role = 'vendor' ORDER BY created_at DESC LIMIT 5;
```

**Expected:** See your vendor account with correct email

---

## 🔍 How to Find Your App ID

### Method 1: From google-services.json
1. Open `vendor_app/android/app/google-services.json`
2. Search for: `"mobilesdk_app_id"`
3. Copy the value after it

**Example:**
```json
{
  "client": [
    {
      "client_info": {
        "mobilesdk_app_id": "1:49227638741:android:c504928647838205757f5c",
        ^^^^^^^^^^^^^^^^^^^^ This is your App ID
      }
    }
  ]
}
```

### Method 2: From Firebase Console
1. Go to Project Settings (gear icon)
2. Scroll to "Your apps"
3. Find "Abra Vendor"
4. App ID is shown there

---

## 🐛 Common Issues & Solutions

### Issue 1: "google-services.json not found"
**Solution:**
- Verify file is in: `vendor_app/android/app/google-services.json`
- NOT in: `vendor_app/android/google-services.json`
- File must be in the `app` subfolder

### Issue 2: "Google Sign-In failed"
**Solution:**
- Add SHA-1 to Firebase Console
- Download fresh google-services.json
- Rebuild app: `flutter clean && flutter build apk`

### Issue 3: "Package name mismatch"
**Solution:**
- Verify package in `android/app/build.gradle`: `com.abraglobal.vendor`
- Verify package in Firebase Console matches exactly
- Case-sensitive!

### Issue 4: "Firebase not initialized"
**Solution:**
- Check `firebase_options.dart` has correct App ID
- Verify `google-services.json` is in correct location
- Run `flutter clean && flutter pub get`

### Issue 5: "SHA-1 certificate error"
**Solution:**
```bash
# Get debug SHA-1:
keytool -list -v -alias androiddebugkey -keystore ~/.android/debug.keystore

# Password: android

# Get release SHA-1:
cd vendor_app/android
keytool -list -v -keystore app/abra-vendor-keystore.jks -alias abra-vendor-key

# Password: abra@vendor2024
```

---

## 📱 Expected File Structure

```
vendor_app/
├── android/
│   ├── app/
│   │   ├── google-services.json  ← Must be here!
│   │   ├── build.gradle           ← Package: com.abraglobal.vendor
│   │   └── src/
│   │       └── main/
│   │           ├── AndroidManifest.xml
│   │           └── kotlin/
│   │               └── com/
│   │                   └── abraglobal/
│   │                       └── vendor/
│   │                           └── MainActivity.kt
│   └── build.gradle
├── lib/
│   ├── firebase_options.dart     ← Update App ID here
│   ├── main.dart
│   └── ...
└── pubspec.yaml
```

---

## ✅ Final Verification

Run this command to verify everything:
```bash
cd vendor_app
flutter doctor -v
flutter pub get
flutter build apk --debug
```

If all steps pass, your Firebase setup is complete! 🎉

---

## 📞 Need Help?

If you encounter issues:
1. Check the error message carefully
2. Verify all checklist items above
3. Try `flutter clean && flutter pub get`
4. Rebuild the app
5. Check Firebase Console for any warnings

**Common Firebase Console URLs:**
- Project Overview: https://console.firebase.google.com/project/abra-customer-and-vendor
- Authentication: https://console.firebase.google.com/project/abra-customer-and-vendor/authentication
- Project Settings: https://console.firebase.google.com/project/abra-customer-and-vendor/settings/general
