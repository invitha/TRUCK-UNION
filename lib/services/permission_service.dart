import 'dart:io';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';

class PermissionService {
  // ── Request all permissions the app needs, called once at startup ──────────
  static Future<void> requestAllPermissions(BuildContext context) async {
    if (!Platform.isAndroid) return;

    // Collect what needs to be requested
    final toRequest = <Permission>[];

    // Notifications (Android 13+)
    if (!await Permission.notification.isGranted) {
      toRequest.add(Permission.notification);
    }

    // Camera
    if (!await Permission.camera.isGranted) {
      toRequest.add(Permission.camera);
    }

    // Storage / Media — Android version aware
    final sdkInt = await _getSdkInt();
    if (sdkInt >= 33) {
      // Android 13+ uses READ_MEDIA_IMAGES instead of READ_EXTERNAL_STORAGE
      if (!await Permission.photos.isGranted) {
        toRequest.add(Permission.photos);
      }
    } else {
      // Android 12 and below
      if (!await Permission.storage.isGranted) {
        toRequest.add(Permission.storage);
      }
    }

    // Location (for driver)
    if (!await Permission.location.isGranted) {
      toRequest.add(Permission.location);
    }

    if (toRequest.isEmpty) return;

    // Request all at once
    final results = await toRequest.request();

    // If any are permanently denied, show settings dialog
    final permanentlyDenied = results.entries
        .where((e) => e.value.isPermanentlyDenied)
        .map((e) => e.key)
        .toList();

    if (permanentlyDenied.isNotEmpty && context.mounted) {
      _showSettingsDialog(context, permanentlyDenied);
    }
  }

  // ── Camera permission check before camera use ──────────────────────────────
  static Future<bool> requestCamera(BuildContext context) async {
    if (!Platform.isAndroid) return true;
    final status = await Permission.camera.request();
    if (status.isPermanentlyDenied && context.mounted) {
      _showSettingsDialog(context, [Permission.camera]);
      return false;
    }
    return status.isGranted;
  }

  // ── Storage/Gallery permission — version aware ─────────────────────────────
  static Future<bool> requestStorage(BuildContext context) async {
    if (!Platform.isAndroid) return true;

    final sdkInt = await _getSdkInt();
    PermissionStatus status;

    if (sdkInt >= 33) {
      status = await Permission.photos.request();
    } else {
      status = await Permission.storage.request();
    }

    if (status.isPermanentlyDenied && context.mounted) {
      _showSettingsDialog(context, [
        sdkInt >= 33 ? Permission.photos : Permission.storage,
      ]);
      return false;
    }
    return status.isGranted;
  }

  // ── Location permission ────────────────────────────────────────────────────
  static Future<bool> requestLocation(BuildContext context) async {
    if (!Platform.isAndroid) return true;
    final status = await Permission.location.request();
    if (status.isPermanentlyDenied && context.mounted) {
      _showSettingsDialog(context, [Permission.location]);
      return false;
    }
    return status.isGranted;
  }

  // ── Notification permission ────────────────────────────────────────────────
  static Future<bool> requestNotification(BuildContext context) async {
    if (!Platform.isAndroid) return true;
    final status = await Permission.notification.request();
    if (status.isPermanentlyDenied && context.mounted) {
      _showSettingsDialog(context, [Permission.notification]);
      return false;
    }
    return status.isGranted;
  }

  // ── Open app settings dialog when permanently denied ──────────────────────
  static void _showSettingsDialog(
      BuildContext context, List<Permission> denied) {
    final names = denied.map(_permissionName).join(', ');
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.lock_outline_rounded, color: Color(0xFF0D2E6E)),
            SizedBox(width: 10),
            Text('Permission Required'),
          ],
        ),
        content: Text(
          '$names permission is required for this feature.\n\nPlease enable it in App Settings.',
          style: const TextStyle(fontSize: 14, height: 1.5),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF0D2E6E),
            ),
            onPressed: () {
              Navigator.pop(ctx);
              openAppSettings();
            },
            child: const Text('Open Settings',
                style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  static String _permissionName(Permission p) {
    if (p == Permission.camera)       return 'Camera';
    if (p == Permission.photos)       return 'Gallery';
    if (p == Permission.storage)      return 'Storage';
    if (p == Permission.location)     return 'Location';
    if (p == Permission.notification) return 'Notifications';
    return 'Required';
  }

  // ── Get Android SDK version ────────────────────────────────────────────────
  static Future<int> _getSdkInt() async {
    try {
      // permission_handler exposes this indirectly; we use a simple check
      // READ_MEDIA_IMAGES was added in API 33
      final status = await Permission.photos.status;
      // If photos permission even exists as a concept, we're on 33+
      // This is a safe approximation — permission_handler returns
      // PermissionStatus.restricted on older Android for this enum
      if (status == PermissionStatus.restricted) return 30; // old Android
      return 33; // new Android
    } catch (_) {
      return 30;
    }
  }
}
