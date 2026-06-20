import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

class DriverTourScreen extends StatefulWidget {
  const DriverTourScreen({super.key});

  @override
  State<DriverTourScreen> createState() => _DriverTourScreenState();
}

class _DriverTourScreenState extends State<DriverTourScreen>
    with TickerProviderStateMixin {
  final PageController _pageController = PageController();
  int _currentPage = 0;
  late AnimationController _iconAnim;
  late Animation<double> _iconScale;

  static const List<_DriverTourPage> _pages = [
    _DriverTourPage(
      bgColor: Color(0xFF0D2E6E),
      accentColor: Color(0xFF60A5FA),
      icon: Icons.waving_hand_rounded,
      iconColor: Color(0xFFFFD700),
      title: 'Welcome Driver!',
      subtitle: 'Driver Bhai, Swagat Hai! 🙏',
      description:
          'You are now part of Truck Union.\nWe will show you how to use this app.\nVery simple — only 5 steps!',
    ),
    _DriverTourPage(
      bgColor: Color(0xFF065F46),
      accentColor: Color(0xFF34D399),
      icon: Icons.assignment_rounded,
      iconColor: Colors.white,
      title: 'See Your Orders',
      subtitle: 'Apna Kaam Dekho',
      description:
          'Tap "My Orders" at the bottom.\nYou will see orders assigned to your truck.\nEach order shows pickup address and delivery address.',
    ),
    _DriverTourPage(
      bgColor: Color(0xFF1E1B4B),
      accentColor: Color(0xFFA78BFA),
      icon: Icons.pin_rounded,
      iconColor: Color(0xFFFCD34D),
      title: 'OTP System',
      subtitle: 'OTP Kya Hai?',
      description:
          '📦 At PICKUP:\nYou will get a 4-digit number (OTP).\nEnter it in the app to confirm pickup.\n\n🏠 At DELIVERY:\nCustomer will give you another OTP.\nEnter it to confirm delivery is done!',
    ),
    _DriverTourPage(
      bgColor: Color(0xFF7C2D12),
      accentColor: Color(0xFFFB923C),
      icon: Icons.navigation_rounded,
      iconColor: Colors.white,
      title: 'Navigate Easily',
      subtitle: 'Rasta Dhundho',
      description:
          'On any order, tap the 📍 Map button.\nGoogle Maps will open with the address.\nFollow the route to reach safely!',
    ),
    _DriverTourPage(
      bgColor: Color(0xFF064E3B),
      accentColor: Color(0xFF10B981),
      icon: Icons.emoji_events_rounded,
      iconColor: Color(0xFFFFD700),
      title: "All Done!",
      subtitle: 'Sab Samajh Gaya! 🎉',
      description:
          'Profile tab → Complete your documents.\nMore documents = more orders!\n\nProblems? Tap Support anytime.\nHappy Driving! 🚛💨',
    ),
  ];

  @override
  void initState() {
    super.initState();
    _iconAnim = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
    _iconScale = CurvedAnimation(parent: _iconAnim, curve: Curves.elasticOut);
    _iconAnim.forward();
  }

  @override
  void dispose() {
    _pageController.dispose();
    _iconAnim.dispose();
    super.dispose();
  }

  void _next() {
    if (_currentPage < _pages.length - 1) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 350),
        curve: Curves.easeInOut,
      );
    } else {
      _finish();
    }
  }

  void _finish() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('driver_tour_shown', true);
    if (mounted) context.go('/driver-dashboard');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          PageView.builder(
            controller: _pageController,
            itemCount: _pages.length,
            onPageChanged: (i) {
              setState(() => _currentPage = i);
              _iconAnim.reset();
              _iconAnim.forward();
            },
            itemBuilder: (_, i) => _buildPage(_pages[i]),
          ),
          // Skip button
          if (_currentPage < _pages.length - 1)
            Positioned(
              top: MediaQuery.of(context).padding.top + 12,
              right: 20,
              child: TextButton(
                onPressed: _finish,
                child: const Text(
                  'Skip',
                  style: TextStyle(
                    color: Colors.white70,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildPage(_DriverTourPage page) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 400),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [page.bgColor, page.bgColor.withOpacity(0.85)],
        ),
      ),
      child: SafeArea(
        child: Column(
          children: [
            const Spacer(flex: 2),

            // Big icon with bounce animation
            ScaleTransition(
              scale: _iconScale,
              child: Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: page.accentColor.withOpacity(0.2),
                  border: Border.all(
                    color: page.accentColor.withOpacity(0.5),
                    width: 3,
                  ),
                ),
                child: Icon(page.icon, size: 80, color: page.iconColor),
              ),
            ),

            const Spacer(flex: 1),

            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(
                page.title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.w900,
                  color: Colors.white,
                  letterSpacing: 0.5,
                ),
              ),
            ),

            const SizedBox(height: 8),

            Text(
              page.subtitle,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: page.accentColor,
              ),
            ),

            const SizedBox(height: 24),

            // Description card
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: Colors.white.withOpacity(0.2)),
                ),
                child: Text(
                  page.description,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 15,
                    color: Colors.white,
                    height: 1.7,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ),

            const Spacer(flex: 2),

            // Dot indicators
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(_pages.length, (i) {
                return AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  margin: const EdgeInsets.symmetric(horizontal: 4),
                  width: i == _currentPage ? 24 : 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: i == _currentPage
                        ? Colors.white
                        : Colors.white.withOpacity(0.4),
                    borderRadius: BorderRadius.circular(4),
                  ),
                );
              }),
            ),

            const SizedBox(height: 32),

            // Next / Done button
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _next,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: page.bgColor,
                    padding: const EdgeInsets.symmetric(vertical: 18),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    elevation: 0,
                  ),
                  child: Text(
                    _currentPage == _pages.length - 1
                        ? '🚛  Start Driving!'
                        : 'Next  →',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: page.bgColor,
                    ),
                  ),
                ),
              ),
            ),

            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }
}

class _DriverTourPage {
  final Color bgColor;
  final Color accentColor;
  final IconData icon;
  final Color iconColor;
  final String title;
  final String subtitle;
  final String description;

  const _DriverTourPage({
    required this.bgColor,
    required this.accentColor,
    required this.icon,
    required this.iconColor,
    required this.title,
    required this.subtitle,
    required this.description,
  });
}
