# Flutter
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }

# Firebase
-keep class com.google.firebase.** { *; }
-keep class com.google.android.gms.** { *; }
-dontwarn com.google.firebase.**

# Facebook SDK
-keep class com.facebook.** { *; }
-dontwarn com.facebook.**

# Kotlin
-keep class kotlin.** { *; }
-dontwarn kotlin.**

# Gson / JSON
-keepattributes Signature
-keepattributes *Annotation*
-dontwarn sun.misc.**

# Keep custom Application class
-keep public class * extends android.app.Application
