import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate {

  private var flutterEngine: FlutterEngine?

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    // Start Flutter engine explicitly so the messenger is live before plugin registration
    let engine = FlutterEngine(name: "main_engine")
    engine.run()
    flutterEngine = engine

    // Register plugins with the running engine
    GeneratedPluginRegistrant.register(with: engine)

    // Build the window programmatically
    window = UIWindow(frame: UIScreen.main.bounds)
    window?.rootViewController = FlutterViewController(engine: engine, nibName: nil, bundle: nil)
    window?.makeKeyAndVisible()

    return true
  }
}
