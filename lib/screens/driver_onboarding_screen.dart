import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';

class DriverOnboardingScreen extends StatefulWidget {
  const DriverOnboardingScreen({super.key});

  @override
  State<DriverOnboardingScreen> createState() => _DriverOnboardingScreenState();
}

class _DriverOnboardingScreenState extends State<DriverOnboardingScreen> {
  final PageController _pageController = PageController();
  int _currentPage = 0;

  static const List<_OnboardingData> _pages = [
    _OnboardingData(
      title: 'Welcome Driver',
      subtitle: 'TRUCK UNION DRIVER',
      description: 'Your trusted partner for seamless deliveries. Track, deliver, and earn with ease.',
      iconType: _IconType.truck,
      iconGradient: [Color(0xFF10B981), Color(0xFF059669)],
    ),
    _OnboardingData(
      title: 'Real-time Tracking',
      subtitle: 'LIVE LOCATION',
      description: 'Your location is tracked in real-time. Operations team monitors your journey for safety.',
      iconType: _IconType.location,
      iconGradient: [Color(0xFFF59E0B), Color(0xFFD97706)],
    ),
    _OnboardingData(
      title: 'Easy Updates',
      subtitle: 'STATUS MANAGEMENT',
      description: 'Update delivery status with one tap. Keep customers and vendors informed instantly.',
      iconType: _IconType.check,
      iconGradient: [Color(0xFF3B82F6), Color(0xFF2563EB)],
    ),
    _OnboardingData(
      title: 'Get Started',
      subtitle: 'LOGIN NOW',
      description: 'Use the credentials provided by your vendor to login and start your first delivery.',
      iconType: _IconType.key,
      iconGradient: [Color(0xFF8B5CF6), Color(0xFF7C3AED)],
    ),
  ];

  @override
  void initState() {
    super.initState();
    SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.light,
    ));
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _onPageChanged(int page) => setState(() => _currentPage = page);

  Future<void> _completeOnboarding() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('has_seen_driver_onboarding', true);
    if (!mounted) return;
    context.go('/driver-login');
  }

  void _next() {
    if (_currentPage < _pages.length - 1) {
      _pageController.nextPage(
        duration: const Duration(milliseconds: 420),
        curve: Curves.easeInOutCubic,
      );
    } else {
      _completeOnboarding();
    }
  }

  @override
  Widget build(BuildContext context) {
    final page = _pages[_currentPage];

    return Scaffold(
      body: AnimatedContainer(
        duration: const Duration(milliseconds: 380),
        curve: Curves.easeInOut,
        color: Colors.white,
        child: SafeArea(
          child: Column(
            children: [
              SizedBox(
                height: 52,
                child: _currentPage < _pages.length - 1
                    ? Align(
                        alignment: Alignment.centerRight,
                        child: Padding(
                          padding: const EdgeInsets.only(right: 20),
                          child: GestureDetector(
                            onTap: _completeOnboarding,
                            child: const Text(
                              'Skip',
                              style: TextStyle(
                                color: Color(0xFF64748B),
                                fontSize: 14,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                        ),
                      )
                    : const SizedBox.shrink(),
              ),
              Expanded(
                child: PageView.builder(
                  controller: _pageController,
                  onPageChanged: _onPageChanged,
                  itemCount: _pages.length,
                  itemBuilder: (_, i) => _PageContent(data: _pages[i]),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 20),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(
                    _pages.length,
                    (i) => _Dot(active: i == _currentPage, color: const Color(0xFF10B981)),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 36),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  width: double.infinity,
                  height: 54,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF10B981), Color(0xFF059669)],
                    ),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Material(
                    color: Colors.transparent,
                    child: InkWell(
                      onTap: _next,
                      borderRadius: BorderRadius.circular(14),
                      child: Center(
                        child: Text(
                          _currentPage == _pages.length - 1 ? 'Get started' : 'Next',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PageContent extends StatelessWidget {
  final _OnboardingData data;
  const _PageContent({required this.data});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 36),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 118,
            height: 118,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                colors: data.iconGradient,
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              boxShadow: [
                BoxShadow(
                  color: data.iconGradient[0].withOpacity(0.3),
                  blurRadius: 20,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Center(
              child: _OnboardingIcon(type: data.iconType, color: Colors.white),
            ),
          ),
          const SizedBox(height: 44),
          Text(
            data.title,
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w700,
              color: Color(0xFF0D2E6E),
              height: 1.2,
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: const Color(0xFF10B981).withOpacity(0.15),
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              data.subtitle,
              style: const TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w700,
                color: Color(0xFF10B981),
                letterSpacing: 1.3,
              ),
            ),
          ),
          const SizedBox(height: 20),
          Text(
            data.description,
            style: const TextStyle(
              fontSize: 15,
              color: Color(0xFF718096),
              height: 1.7,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}

class _Dot extends StatelessWidget {
  final bool active;
  final Color color;
  const _Dot({required this.active, required this.color});

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 300),
      margin: const EdgeInsets.symmetric(horizontal: 4),
      width: active ? 22 : 7,
      height: 7,
      decoration: BoxDecoration(
        color: active ? color : const Color(0xFFE2E8F0),
        borderRadius: BorderRadius.circular(4),
      ),
    );
  }
}

enum _IconType { truck, location, check, key }

class _OnboardingIcon extends StatelessWidget {
  final _IconType type;
  final Color color;
  const _OnboardingIcon({required this.type, required this.color});

  @override
  Widget build(BuildContext context) {
    final IconData icon;
    switch (type) {
      case _IconType.truck:
        icon = Icons.local_shipping_outlined;
      case _IconType.location:
        icon = Icons.location_on_outlined;
      case _IconType.check:
        icon = Icons.check_circle_outline;
      case _IconType.key:
        icon = Icons.vpn_key_outlined;
    }
    return Icon(icon, size: 50, color: color);
  }
}

class _OnboardingData {
  final String title;
  final String subtitle;
  final String description;
  final _IconType iconType;
  final List<Color> iconGradient;

  const _OnboardingData({
    required this.title,
    required this.subtitle,
    required this.description,
    required this.iconType,
    required this.iconGradient,
  });
}
