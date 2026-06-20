# Easy SHA-1 Certificate Guide

## 🎯 Simple 2-Step Process

### **Step 1: Run the Script**

Double-click this file in your `vendor_app` folder:
```
get_sha1.bat
```

This will automatically show you your SHA-1 certificates!

---

### **Step 2: Copy the SHA-1**

You'll see output like this:

```
[1] DEBUG SHA-1 (For Testing):
----------------------------------------
SHA1: AA:BB:CC:DD:EE:FF:11:22:33:44:55:66:77:88:99:00:AA:BB:CC:DD
      ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
      Copy this entire value!
```

**Copy the entire SHA-1 value** (the part after "SHA1:")

---

## 📋 What to Do Next

### **Add SHA-1 to Firebase:**

1. **Go to Firebase Console:**
   ```
   https://console.firebase.google.com/project/abra-customer-and-vendor/settings/general
   ```

2. **Scroll down to "Your apps"**

3. **Find "Abra Vendor Android"**

4. **Look for "SHA certificate fingerprints" section**

5. **Click "Add fingerprint"**

6. **Paste your SHA-1** (the value you copied)

7. **Click "Save"**

---

## 🔧 Troubleshooting

### **If get_sha1.bat doesn't work:**

#### **Option 1: Manual Command (Debug)**
Open Command Prompt and run:
```cmd
keytool -list -v -alias androiddebugkey -keystore "%USERPROFILE%\.android\debug.keystore" -storepass android
```

Look for the line that says `SHA1:` and copy that value.

#### **Option 2: Generate Release Keystore First**
If you don't have a release keystore yet, run:
```
generate_keystore.bat
```

Then run `get_sha1.bat` again.

---

## 📝 Two Types of SHA-1

### **1. DEBUG SHA-1** (For Testing)
- Used when you run `flutter run`
- Automatically created by Android Studio
- Located at: `%USERPROFILE%\.android\debug.keystore`
- **Use this for testing your app**

### **2. RELEASE SHA-1** (For Production)
- Used when you build release APK
- You need to create this manually
- Located at: `vendor_app/android/app/abra-vendor-keystore.jks`
- **Use this when publishing to Play Store**

---

## ✅ Quick Checklist

- [ ] Ran `get_sha1.bat`
- [ ] Copied SHA-1 value
- [ ] Opened Firebase Console
- [ ] Found "Abra Vendor Android" app
- [ ] Clicked "Add fingerprint"
- [ ] Pasted SHA-1
- [ ] Clicked "Save"
- [ ] Downloaded `google-services.json`
- [ ] Placed in `vendor_app/android/app/google-services.json`

---

## 🎉 Done!

Once you've added the SHA-1 to Firebase:
1. Download `google-services.json` from Firebase
2. Place it in `vendor_app/android/app/google-services.json`
3. Run: `flutter pub get`
4. Run: `flutter run`

Your app should now work with Google Sign-In! 🚀

---

## 💡 Pro Tip

**Add BOTH SHA-1 certificates to Firebase:**
- Debug SHA-1 (for testing during development)
- Release SHA-1 (for production builds)

This way, Google Sign-In will work in both debug and release modes!
