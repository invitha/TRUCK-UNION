#import <Flutter/Flutter.h>

NS_ASSUME_NONNULL_BEGIN

/// iOS 26 compatible plugin registrant.
/// Registers ONLY Objective-C plugins. Swift plugins are excluded because
/// they crash with swift_getObjectType on iOS 26 (Flutter SDK binary incompatibility).
/// This file replaces GeneratedPluginRegistrant in AppDelegate — it is never overwritten by Flutter tools.
@interface SafePluginRegistrant : NSObject
+ (void)registerWithRegistry:(NSObject<FlutterPluginRegistry>*)registry;
@end

NS_ASSUME_NONNULL_END
