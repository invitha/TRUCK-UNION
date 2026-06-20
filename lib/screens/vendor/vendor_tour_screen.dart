import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

class VendorTourScreen extends StatefulWidget {
  const VendorTourScreen({super.key});

  @override
  State<VendorTourScreen> createState() => _VendorTourScreenState();
}

class _VendorTourScreenState extends State<VendorTourScreen>
    with TickerProviderStateMixin {
  final PageController _pageController = PageController();
  int _currentPage = 0;
  late AnimationController _iconAnim;
  late Animation<double> _iconScale;

  static const List<_TourPage> _pages = [
    _TourPage(
      bgColor: Color(0xFF0D2E6E),
      accentColor: Color(0xFF3B82F6),
      icon: Icons.waving_hand_rounded,
      iconColor: Color(0xFFFFD700),
      title: 'Welcome!',
      subtitle: 'Aapka Swagat Hai 🙏',
      description:
          'You have joined Truck Union.\nWe will show you how to use this app step by step.\nIt is very easy!',
    ),
    _TourPage(
      bgColor: Color(0xFF065F46),
      accentColor: Color(0xFF10B981),
      icon: Icons.local_shipping_rounded,
      iconColor: Colors.white,
      title: 'Add Your Truck',
      subtitle: 'Apna Truck Add Karein',
      description:
          'Tap the "My Fleet" button at the bottom.\nEnter your truck number, model name, and driver details.\nYour truck will be registered!',
    ),
    _TourPage(
      bgColor: Color(0xFF1E3A5F),
      accentColor: Color(0xFF60A5FA),
      icon: Icons.inventory_2_rounded,
      iconColor: Color(0xFFFCD34D),
      title: 'Track Orders',
      subtitle: 'Orders Dekhein',
      description:
          'Tap "My Orders" at the bottom.\nSee all shipments assigned to your trucks.\nCheck pickup and delivery status anytime.',
    ),
    _TourPage(
      bgColor: Color(0xFF4C1D95),
      accentColor: Color(0xFFA78BFA),
      icon: Icons.notifications_active_rounded,
      iconColor: Color(0xFFFCD34D),
      title: 'Get Alerts',
      subtitle: 'Notification Milegi',
      description:
          'We will send you a message when:\n• A truck gets a new order\n• Delivery is completed\n• Payment is done',
    ),
    _TourPage(
      bgColor: Color(0xFF7C2D12),
      accentColor: Color(0xFFF97316),
      icon: Icons.verified_user_rounded,
      iconColor: Colors.white,
      title: 'Verify Account',
      subtitle: 'KYC Complete Karein',
      description:
          'Go to Profile → KYC Verification.\nUpload your documents.\nAfter approval, you will start getting orders!',
    ),
    _TourPage(
      bgColor: Color(0xFF064E3B),
      accentColor: Color(0xFF34D399),
      icon: Icons.rocket_launch_rounded,
      iconColor: Color(0xFFFFD700),
      title: "You're Ready!",
      subtitle: 'Sab Set Ho Gaya! 🎉',
      description:
          'Start by adding your first truck.\nNeed help anytime? Tap the Chat button — our team is always ready.\nGood Luck! 💪',
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
    await prefs.setBool('vendor_tour_shown', true);
    if (mounted) context.go('/vendor');
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
          // Skip button top right
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

  Widget _buildPage(_TourPage page) {
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

            // Big icon with bounce
            ScaleTransition(
              scale: _iconScale,
              child: Container(
                width: 160,
                height: 160,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: page.accentColor.withOpacity(0.2),
                  border: Border.all(color: page.accentColor.withOpacity(0.5), width: 3),
                ),
                child: Icon(page.icon, size: 80, color: page.iconColor),
              ),
            ),

            const Spacer(flex: 1),

            // Title
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

            // Subtitle (Hindi/English)
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

            // Next / Start button
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
                    _currentPage == _pages.length - 1 ? '🚀  Start Now!' : 'Next  →',
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

class _TourPage {
  final Color bgColor;
  final Color accentColor;
  final IconData icon;
  final Color iconColor;
  final String title;
  final String subtitle;
  final String description;

  const _TourPage({
    required this.bgColor,
    required this.accentColor,
    required this.icon,
    required this.iconColor,
    required this.title,
    required this.subtitle,
    required this.description,
  });
}
