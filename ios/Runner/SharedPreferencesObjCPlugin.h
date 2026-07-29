#import <Flutter/Flutter.h>

NS_ASSUME_NONNULL_BEGIN

/// ObjC replacement for shared_preferences_foundation (Swift plugin).
/// Uses NSUserDefaults directly — identical behaviour, no Swift runtime required.
/// Registered in AppDelegate to handle channel "plugins.flutter.io/shared_preferences".
@interface SharedPreferencesObjCPlugin : NSObject <FlutterPlugin>
@end

NS_ASSUME_NONNULL_END
