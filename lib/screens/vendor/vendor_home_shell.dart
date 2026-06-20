import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:tutorial_coach_mark/tutorial_coach_mark.dart';
import '../../config/app_theme.dart';

class VendorHomeShell extends StatefulWidget {
  final Widget child;
  const VendorHomeShell({super.key, required this.child});

  @override
  State<VendorHomeShell> createState() => VendorHomeShellState();
}

class VendorHomeShellState extends State<VendorHomeShell> {
  // ── GlobalKeys for tour highlights ─────────────────────────────────────────
  static final GlobalKey dashboardKey = GlobalKey(debugLabel: 'tab_dashboard');
  static final GlobalKey fleetKey     = GlobalKey(debugLabel: 'tab_fleet');
  static final GlobalKey assignedKey  = GlobalKey(debugLabel: 'tab_assigned');
  static final GlobalKey supportKey   = GlobalKey(debugLabel: 'tab_support');
  static final GlobalKey profileKey   = GlobalKey(debugLabel: 'tab_profile');

  int _calculateSelectedIndex(BuildContext context) {
    final path = GoRouterState.of(context).uri.path;
    if (path.startsWith('/vendor/vehicles'))       return 1;
    if (path.startsWith('/vendor/assigned-fleets')) return 2;
    if (path.startsWith('/vendor/chat'))           return 3;
    if (path.startsWith('/vendor/profile'))        return 4;
    return 0;
  }

  void _onItemTapped(BuildContext context, int index) {
    switch (index) {
      case 0: context.go('/vendor');                    break;
      case 1: context.go('/vendor/vehicles');           break;
      case 2: context.go('/vendor/assigned-fleets');    break;
      case 3: context.go('/vendor/chat');               break;
      case 4: context.go('/vendor/profile');            break;
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _checkAndShowTour());
  }

  Future<void> _checkAndShowTour() async {
    final prefs = await SharedPreferences.getInstance();
    final shown = prefs.getBool('vendor_tour_shown') ?? false;
    if (!shown && mounted) {
      await Future.delayed(const Duration(milliseconds: 800));
      if (mounted) _launchTour();
    }
  }

  void _launchTour() {
    final targets = <TargetFocus>[
      // 1 — Dashboard
      TargetFocus(
        identify: 'tab_dashboard',
        keyTarget: dashboardKey,
        shape: ShapeLightFocus.RRect,
        radius: 12,
        paddingFocus: 10,
        contents: [
          TargetContent(
            align: ContentAlign.top,
            builder: (_, __) => _TourCard(
              step: '1 / 5',
              icon: Icons.dashboard_rounded,
              title: 'Dashboard — Home Screen',
              body: 'Your main screen.\nSee vehicle count, order stats, and quick actions here.',
            ),
          ),
        ],
      ),

      // 2 — My Fleet
      TargetFocus(
        identify: 'tab_fleet',
        keyTarget: fleetKey,
        shape: ShapeLightFocus.RRect,
        radius: 12,
        paddingFocus: 10,
        contents: [
          TargetContent(
            align: ContentAlign.top,
            builder: (_, __) => _TourCard(
              step: '2 / 5',
              icon: Icons.local_shipping_rounded,
              title: 'My Fleet — Add Trucks',
              body: 'Tap here to add your truck.\nEnter truck number, model, driver name & mobile.\nAll your vehicles are listed here.',
            ),
          ),
        ],
      ),

      // 3 — Assigned Orders
      TargetFocus(
        identify: 'tab_assigned',
        keyTarget: assignedKey,
        shape: ShapeLightFocus.RRect,
        radius: 12,
        paddingFocus: 10,
        contents: [
          TargetContent(
            align: ContentAlign.top,
            builder: (_, __) => _TourCard(
              step: '3 / 5',
              icon: Icons.assignment_rounded,
              title: 'Orders — Track Deliveries',
              body: 'See all shipments assigned to your trucks.\nCheck Pending → Active → Done status.\nTap any order to see full details.',
            ),
          ),
        ],
      ),

      // 4 — Support
      TargetFocus(
        identify: 'tab_support',
        keyTarget: supportKey,
        shape: ShapeLightFocus.RRect,
        radius: 12,
        paddingFocus: 10,
        contents: [
          TargetContent(
            align: ContentAlign.top,
            builder: (_, __) => _TourCard(
              step: '4 / 5',
              icon: Icons.support_agent_rounded,
              title: 'Support — We Are Here!',
              body: 'Any problem? Tap here and chat with our team.\nWe reply fast. Available anytime.',
            ),
          ),
        ],
      ),

      // 5 — Profile
      TargetFocus(
        identify: 'tab_profile',
        keyTarget: profileKey,
        shape: ShapeLightFocus.RRect,
        radius: 12,
        paddingFocus: 10,
        contents: [
          TargetContent(
            align: ContentAlign.top,
            builder: (_, __) => _TourCard(
              step: '5 / 5',
              icon: Icons.verified_user_rounded,
              title: 'Profile & KYC Verification',
              body: 'Go here to complete your KYC.\nUpload your documents to get verified.\nAfter approval — orders start coming! ✅',
              isLast: true,
            ),
          ),
        ],
      ),
    ];

    TutorialCoachMark(
      targets: targets,
      colorShadow: const Color(0xFF0D2E6E),
      opacityShadow: 0.92,
      textSkip: 'SKIP TOUR',
      textStyleSkip: const TextStyle(
        color: Colors.white,
        fontSize: 14,
        fontWeight: FontWeight.w700,
      ),
      paddingFocus: 8,
      onFinish: _markTourDone,
      onSkip: () { _markTourDone(); return true; },
    ).show(context: context);
  }

  Future<void> _markTourDone() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('vendor_tour_shown', true);
  }

  @override
  Widget build(BuildContext context) {
    final currentIndex = _calculateSelectedIndex(context);
    return PopScope(
      canPop: false,
      onPopInvoked: (didPop) {
        if (!didPop && currentIndex != 0) context.go('/vendor');
      },
      child: Scaffold(
        body: widget.child,
        bottomNavigationBar: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.1),
                blurRadius: 10,
                offset: const Offset(0, -2),
              ),
            ],
          ),
          child: SafeArea(
            top: false,
            child: Row(
              children: [
                _buildNavItem(
                  key: dashboardKey,
                  context: context,
                  icon: Icons.dashboard_rounded,
                  label: 'Dashboard',
                  index: 0,
                  currentIndex: currentIndex,
                ),
                _buildNavItem(
                  key: fleetKey,
                  context: context,
                  icon: Icons.local_shipping_rounded,
                  label: 'My Fleet',
                  index: 1,
                  currentIndex: currentIndex,
                ),
                _buildNavItem(
                  key: assignedKey,
                  context: context,
                  icon: Icons.assignment_rounded,
                  label: 'Assigned',
                  index: 2,
                  currentIndex: currentIndex,
                ),
                _buildNavItem(
                  key: supportKey,
                  context: context,
                  icon: Icons.support_agent_rounded,
                  label: 'Support',
                  index: 3,
                  currentIndex: currentIndex,
                ),
                _buildNavItem(
                  key: profileKey,
                  context: context,
                  icon: Icons.person_rounded,
                  label: 'Profile',
                  index: 4,
                  currentIndex: currentIndex,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem({
    required GlobalKey key,
    required BuildContext context,
    required IconData icon,
    required String label,
    required int index,
    required int currentIndex,
  }) {
    final bool isSelected = index == currentIndex;
    return Expanded(
      child: GestureDetector(
        key: key,
        onTap: () => _onItemTapped(context, index),
        behavior: HitTestBehavior.opaque,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: isSelected
                      ? AppTheme.primaryBlue.withOpacity(0.12)
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  icon,
                  size: 24,
                  color: isSelected ? AppTheme.primaryBlue : Colors.grey,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                label,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                  color: isSelected ? AppTheme.primaryBlue : Colors.grey,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ── Tour tooltip card ───────────────────────────────────────────────────────

class _TourCard extends StatelessWidget {
  final String step;
  final IconData icon;
  final String title;
  final String body;
  final bool isLast;

  const _TourCard({
    required this.step,
    required this.icon,
    required this.title,
    required this.body,
    this.isLast = false,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.3),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          // Step badge + icon
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: AppTheme.primaryBlue.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  step,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.primaryBlue,
                  ),
                ),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  gradient: AppTheme.primaryGradient,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: Colors.white, size: 20),
              ),
            ],
          ),
          const SizedBox(height: 12),
          // Title
          Text(
            title,
            style: const TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.w800,
              color: Color(0xFF0D2E6E),
            ),
          ),
          const SizedBox(height: 8),
          // Body
          Text(
            body,
            style: TextStyle(
              fontSize: 14,
              color: Colors.grey[700],
              height: 1.5,
            ),
          ),
          if (isLast) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(vertical: 10),
              decoration: BoxDecoration(
                gradient: AppTheme.primaryGradient,
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Center(
                child: Text(
                  "🚀  You're All Set!",
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                  ),
                ),
              ),
            ),
          ],
          const SizedBox(height: 8),
          Text(
            isLast ? 'Tap anywhere to finish' : 'Tap anywhere to continue →',
            style: TextStyle(fontSize: 12, color: Colors.grey[500]),
          ),
        ],
      ),
    );
  }
}
