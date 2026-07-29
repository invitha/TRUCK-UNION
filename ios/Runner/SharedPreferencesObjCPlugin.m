#import "SharedPreferencesObjCPlugin.h"

static NSString *const kChannel = @"plugins.flutter.io/shared_preferences";
static NSString *const kPrefix  = @"flutter.";

@implementation SharedPreferencesObjCPlugin

+ (void)registerWithRegistrar:(NSObject<FlutterPluginRegistrar> *)registrar {
    FlutterMethodChannel *channel =
        [FlutterMethodChannel methodChannelWithName:kChannel
                                    binaryMessenger:[registrar messenger]];
    SharedPreferencesObjCPlugin *instance = [[SharedPreferencesObjCPlugin alloc] init];
    [registrar addMethodCallDelegate:instance channel:channel];
}

- (void)handleMethodCall:(FlutterMethodCall *)call result:(FlutterResult)result {
    NSUserDefaults *ud  = [NSUserDefaults standardUserDefaults];
    NSDictionary   *args = call.arguments;

    if ([call.method isEqualToString:@"getAll"]) {
        NSDictionary *all = [ud dictionaryRepresentation];
        NSMutableDictionary *out = [NSMutableDictionary dictionary];
        for (NSString *key in all) {
            if ([key hasPrefix:kPrefix]) out[key] = all[key];
        }
        result(out);

    } else if ([call.method isEqualToString:@"setBool"]) {
        [ud setBool:[args[@"value"] boolValue] forKey:args[@"key"]];
        result(@YES);

    } else if ([call.method isEqualToString:@"setInt"]) {
        [ud setObject:args[@"value"] forKey:args[@"key"]];
        result(@YES);

    } else if ([call.method isEqualToString:@"setDouble"]) {
        [ud setDouble:[args[@"value"] doubleValue] forKey:args[@"key"]];
        result(@YES);

    } else if ([call.method isEqualToString:@"setString"]) {
        [ud setObject:args[@"value"] forKey:args[@"key"]];
        result(@YES);

    } else if ([call.method isEqualToString:@"setStringList"]) {
        [ud setObject:args[@"value"] forKey:args[@"key"]];
        result(@YES);

    } else if ([call.method isEqualToString:@"remove"]) {
        [ud removeObjectForKey:args[@"key"]];
        result(@YES);

    } else if ([call.method isEqualToString:@"clear"]) {
        NSDictionary *all = [ud dictionaryRepresentation];
        for (NSString *key in all) {
            if ([key hasPrefix:kPrefix]) [ud removeObjectForKey:key];
        }
        result(@YES);

    } else {
        result(FlutterMethodNotImplemented);
    }
}

@end
