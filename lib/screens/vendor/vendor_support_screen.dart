import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:go_router/go_router.dart';

class VendorSupportScreen extends StatelessWidget {
  const VendorSupportScreen({super.key});

  Future<void> _makePhoneCall(BuildContext context) async {
    final Uri phoneUri = Uri(scheme: 'tel', path: '+919740231041');
    try {
      if (await canLaunchUrl(phoneUri)) {
        await launchUrl(phoneUri);
      } else {
        throw 'Could not launch phone app';
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('Call us at: +91 9740 231 041'),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
      }
    }
  }

  Future<void> _openWhatsApp(BuildContext context) async {
    final whatsappNumber = '919740231041';
    final message = 'Hello, I need help with TRUCK UNION vendor services';
    final Uri whatsappUri = Uri.parse('https://wa.me/$whatsappNumber?text=${Uri.encodeComponent(message)}');
    
    try {
      if (await canLaunchUrl(whatsappUri)) {
        await launchUrl(whatsappUri, mode: LaunchMode.externalApplication);
      } else {
        throw 'Could not launch WhatsApp';
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('WhatsApp us at: +91 9740 231 041'),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
      }
    }
  }

  Future<void> _sendEmail(BuildContext context) async {
    final Uri emailUri = Uri(
      scheme: 'mailto',
      path: 'support@abra-logistic.com',
      query: 'subject=Vendor Support Request&body=Hello TRUCK UNION Team,\n\nI need assistance with:\n\n',
    );
    
    try {
      if (await canLaunchUrl(emailUri)) {
        await launchUrl(emailUri);
      } else {
        throw 'Could not launch email app';
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Email us at: support@abra-logistic.com'),
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    }
  }

  void _openChat(BuildContext context) {
    context.go('/vendor/chat');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_rounded, color: Color(0xFF0D2E6E)),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/vendor');
            }
          },
        ),
        title: const Text(
          'Vendor Support',
          style: TextStyle(
            color: Color(0xFF0D2E6E),
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        centerTitle: true,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header Section
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF0D2E6E), Color(0xFF1E88E5)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  children: [
                    const Icon(
                      Icons.headset_mic_rounded,
                      size: 40,
                      color: Colors.white,
                    ),
                    const SizedBox(height: 12),
                    const Text(
                      'We\'re Here to Help!',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Choose your preferred way to contact us',
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.9),
                        fontSize: 13,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
              
              const SizedBox(height: 24),
              
              // Support Options - Vertical List
              const Text(
                'Contact Options',
                style: TextStyle(
                  color: Color(0xFF0D2E6E),
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              
              const SizedBox(height: 16),
              
              // Support Options List
              _SupportListItem(
                icon: Icons.phone_rounded,
                title: 'Call Us',
                subtitle: '24/7 Support - +91 9740 231 041',
                color: const Color(0xFF2E7D32),
                onTap: () => _makePhoneCall(context),
              ),
              
              const SizedBox(height: 12),
              
              _SupportListItem(
                icon: Icons.chat_rounded,
                title: 'WhatsApp',
                subtitle: 'Quick chat - +91 9740 231 041',
                color: const Color(0xFF25D366),
                onTap: () => _openWhatsApp(context),
              ),
              
              const SizedBox(height: 12),
              
              _SupportListItem(
                icon: Icons.email_rounded,
                title: 'Email',
                subtitle: 'Write to us - support@abra-logistic.com',
                color: const Color(0xFF1976D2),
                onTap: () => _sendEmail(context),
              ),
              
              const SizedBox(height: 12),
              
              _SupportListItem(
                icon: Icons.support_agent_rounded,
                title: 'Live Chat',
                subtitle: 'Instant AI assistant help',
                color: const Color(0xFF7B1FA2),
                onTap: () => _openChat(context),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SupportListItem extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  const _SupportListItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withOpacity(0.2)),
          boxShadow: [
            BoxShadow(
              color: color.withOpacity(0.08),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: color,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 22, color: Colors.white),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: color,
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: Color(0xFF718096),
                      fontSize: 12,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Icon(
              Icons.arrow_forward_ios_rounded,
              size: 14,
              color: color.withOpacity(0.6),
            ),
          ],
        ),
      ),
    );
  }
}
