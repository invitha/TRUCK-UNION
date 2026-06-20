import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'dart:async';
import '../../config/app_theme.dart';

class ChatMessage {
  final String id;
  final String message;
  final String senderName;
  final bool isFromUser;
  final DateTime timestamp;

  ChatMessage({
    required this.id,
    required this.message,
    required this.senderName,
    required this.isFromUser,
    required this.timestamp,
  });
}

class VendorChatScreen extends StatefulWidget {
  const VendorChatScreen({super.key});

  @override
  State<VendorChatScreen> createState() => _VendorChatScreenState();
}

class _VendorChatScreenState extends State<VendorChatScreen> {
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  String? _currentUserId;
  String? _currentUserName;
  List<ChatMessage> _messages = [];
  bool _isSending = false;
  bool _hasShownWelcome = false;

  @override
  void initState() {
    super.initState();
    _initializeUser();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _showWelcomeMessageWithSound();
    });
  }

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _initializeUser() {
    final user = FirebaseAuth.instance.currentUser;
    if (user != null) {
      _currentUserId = user.uid;
      _currentUserName = user.displayName ?? 'Vendor';
    }
  }

  Future<void> _showWelcomeMessageWithSound() async {
    if (_hasShownWelcome || _currentUserId == null) return;
    
    _hasShownWelcome = true;
    await Future.delayed(const Duration(milliseconds: 300));
    
    if (!mounted) return;
    
    try {
      await _playNotificationSound();
      
      final welcomeMessage = ChatMessage(
        id: 'welcome_${DateTime.now().millisecondsSinceEpoch}',
        message: '👋 Hello! Welcome to TRUCK UNION Vendor Support!\n\nI can help you with:\n• 🚚 Fleet management & vehicle queries\n• 📦 Order & shipment information\n• 💰 Payment & earnings details\n• 📋 KYC & verification assistance\n• 🎯 Performance analytics\n• 📞 General support\n\nFor urgent matters:\n📞 Call +91 9740 231 041\n\nHow can I assist you today?',
        senderName: 'TRUCK UNION Support',
        isFromUser: false,
        timestamp: DateTime.now(),
      );
      
      setState(() {
        _messages = [welcomeMessage];
      });
      
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _scrollToBottom();
      });
      
    } catch (e) {
      print('Error showing welcome message: $e');
    }
  }

  Future<void> _simulateAutoReply() async {
    if (_messages.isEmpty) return;
    
    final lastMessage = _messages.last;
    if (!lastMessage.isFromUser) return;
    
    try {
      await _playNotificationSound();
      
      final autoReply = _generateAutoReply(lastMessage.message);
      final replyMessage = ChatMessage(
        id: 'reply_${DateTime.now().millisecondsSinceEpoch}',
        message: autoReply,
        senderName: 'TRUCK UNION Support',
        isFromUser: false,
        timestamp: DateTime.now(),
      );
      
      setState(() {
        _messages.add(replyMessage);
      });
      
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _scrollToBottom();
      });
      
    } catch (e) {
      print('Error in auto reply: $e');
    }
  }

  String _generateAutoReply(String message) {
    final msg = message.toLowerCase().trim();
    
    final responses = {
      'hello': 'Hello! How can I help you today? I can assist with fleet management, orders, payments, and KYC queries.',
      'hi': 'Hi there! I can help you with vehicle management, order tracking, earnings, and account information. What do you need?',
      'help': 'I can help you with:\n• 🚚 Add & manage vehicles\n• 📦 Track orders & shipments\n• 💰 View earnings & payments\n• 📋 KYC verification\n• 📊 Performance analytics\n• 🎯 General support\n\nWhat would you like to know?',
      
      // Fleet Management
      'vehicle': 'To manage your vehicles:\n1. Go to "My Fleet" tab\n2. Add single vehicle or bulk upload\n3. View all your vehicles\n4. Track vehicle status\n\nNeed help adding vehicles? Call +91 9740 231 041',
      'fleet': 'Fleet management features:\n• Add single vehicles\n• Bulk upload multiple vehicles\n• View all vehicles with status\n• Track vehicle performance\n• Update vehicle details\n\nUse the "My Fleet" tab to manage your fleet.',
      'add': 'To add a vehicle:\n1. Complete KYC verification first\n2. Go to "My Fleet" tab\n3. Click "Add Single Vehicle"\n4. Enter vehicle details\n5. Submit for verification\n\nBulk upload available for multiple vehicles.',
      'bulk': 'Bulk upload vehicles:\n1. Complete KYC verification\n2. Go to "My Fleet" tab\n3. Click "Bulk Upload"\n4. Upload CSV file with vehicle details\n5. System processes all vehicles\n\nFaster way to add multiple vehicles!',
      'upload': 'Upload vehicles via:\n• Single vehicle form (one at a time)\n• Bulk upload (CSV file for multiple)\n\nBoth options available in "My Fleet" tab after KYC verification.',
      
      // Orders & Shipments
      'order': 'Orders are coordinated by our internal team:\n1. Complete KYC verification\n2. Add vehicles to your fleet\n3. Our team will contact you with order details\n4. Accept orders via phone/WhatsApp\n5. Complete delivery and get paid\n\nFor order queries, call +91 9740 231 041',
      'orders': 'Our internal team coordinates all orders:\n• They contact you with shipment details\n• You accept/reject via phone\n• Team assigns orders based on availability\n• You complete delivery\n• Earn commission per order\n\nCall +91 9740 231 041 for order coordination.',
      'shipment': 'Shipment coordination:\n• Internal team contacts you with details\n• Pickup & delivery locations shared\n• You confirm availability\n• Complete the shipment\n• Update delivery status\n• Get paid automatically\n\nTeam coordinates everything!',
      'track': 'Track your shipments:\n• Team provides shipment details\n• You update pickup status\n• Share delivery progress\n• Confirm completion\n• Track earnings\n\nCoordination via phone/WhatsApp.',
      'status': 'Update shipment status:\n• Inform team about pickup\n• Share transit updates\n• Confirm delivery completion\n• Team updates customers\n\nCall +91 9740 231 041 for status updates.',
      'delivery': 'For deliveries:\n• Team assigns shipment to you\n• You confirm availability\n• Complete pickup and delivery\n• Inform team upon completion\n• Earn commission\n\nTeam handles all coordination!',
      'accept': 'To accept orders:\n1. Team calls/WhatsApps with order details\n2. Check route and requirements\n3. Confirm availability to team\n4. Complete the delivery\n5. Get paid\n\nAll coordination via team at +91 9740 231 041',
      'booking': 'Order booking process:\n• Customers book shipments\n• Our internal team reviews\n• Team contacts suitable vendors\n• You confirm availability\n• Team coordinates pickup/delivery\n• You complete and get paid\n\nTeam manages everything!',
      
      // KYC & Verification
      'kyc': 'For KYC verification:\n1. Go to "My Fleet" tab\n2. Click "Start KYC"\n3. Upload required documents:\n   • Aadhaar card\n   • PAN card\n   • Vehicle registration\n4. Submit for review\n\nKYC required to add vehicles. Approval within 24-48 hours.',
      'verification': 'KYC verification process:\n• Upload Aadhaar & PAN\n• Provide vehicle documents\n• Submit for review\n• Approval within 24-48 hours\n• Get notified when approved\n\nRequired to start earning!',
      'document': 'Required KYC documents:\n• Aadhaar card (front & back)\n• PAN card\n• Vehicle registration certificate\n• Driving license\n• Address proof\n\nAll uploads are secure and encrypted.',
      'approve': 'KYC approval timeline:\n• Submitted → Under Review (24 hours)\n• Verified → Approved (24-48 hours)\n• You\'ll get notification when approved\n• Can add vehicles after approval\n\nCheck status in "My Fleet" tab.',
      
      // Earnings & Payments
      'earning': 'View your earnings:\n• Dashboard shows total earnings\n• Commission per completed shipment\n• Bonus for high ratings\n• Real-time earnings tracker\n\nEarnings credited to your account weekly.',
      'earnings': 'Earnings breakdown:\n• Base commission per shipment\n• Bonus for on-time delivery\n• Incentives for high ratings\n• Weekly payouts\n• View detailed earnings report\n\nCheck dashboard for current earnings.',
      'payment': 'Payment details:\n• Weekly payouts to your account\n• Automatic bank transfer\n• View payment history\n• Track pending payments\n• Transparent commission structure\n\nPayments processed every Friday.',
      'pay': 'Get paid for completed shipments:\n• Accept order\n• Complete delivery\n• Earn commission\n• Weekly automatic payout\n• View earnings anytime\n\nMore orders = More earnings!',
      'commission': 'Commission structure:\n• Base rate per shipment\n• Bonus for on-time delivery\n• Incentives for high ratings\n• Varies by route & vehicle type\n\nCall +91 9740 231 041 for detailed rates.',
      'payout': 'Payout process:\n• Shipment completed\n• Commission calculated\n• Weekly automatic transfer\n• Direct to your bank account\n• View history in dashboard\n\nPayments every Friday!',
      
      // Performance & Analytics
      'analytics': 'View your performance:\n• Total shipments completed\n• On-time delivery rate\n• Customer ratings\n• Earnings summary\n• Performance trends\n\nBetter performance = More orders!',
      'rating': 'Improve your rating:\n• On-time pickups & deliveries\n• Professional communication\n• Vehicle maintenance\n• Customer satisfaction\n• Accurate status updates\n\nHigh ratings get priority orders!',
      'performance': 'Performance metrics:\n• Completion rate\n• On-time delivery %\n• Customer ratings (1-5 stars)\n• Response time\n• Cancellation rate\n\nTrack in dashboard analytics.',
      
      // Support & Contact
      'support': 'Get support through:\n• This chat for quick help\n• Call +91 9740 231 041 for urgent issues\n• Email support available\n• Dashboard help section\n\nWhat specific help do you need?',
      'contact': 'Contact TRUCK UNION:\n📞 +91 9740 231 041\n💬 This chat for quick help\n📧 Email support\n🏢 Dashboard help section\n\nHow can I assist you?',
      'phone': 'Call us at +91 9740 231 041 for immediate assistance!',
      'call': 'For urgent matters, call +91 9740 231 041. Our team is ready to help!',
      
      // Account & Profile
      'profile': 'Manage your profile:\n• View personal information\n• Update contact details\n• View KYC status\n• Check earnings\n• Manage preferences\n\nProfile settings in dashboard.',
      'account': 'Your account features:\n• Personal information\n• KYC verification status\n• Earnings & payments\n• Performance metrics\n• Support options',
      'edit': 'Edit your profile:\n• Update phone number\n• Change email\n• Update bank details\n• Modify preferences\n\nGo to profile settings in dashboard.',
      
      // Common Issues
      'problem': 'Sorry to hear about the issue. For quick resolution, please call +91 9740 231 041. Our team will prioritize your concern.',
      'issue': 'For any issues, calling +91 9740 231 041 gets fastest resolution.',
      'complaint': 'For complaints, please call +91 9740 231 041 immediately. Our team will handle your concern with priority.',
      'urgent': 'For urgent matters, call +91 9740 231 041 right away.',
      'error': 'For app errors:\n• Try restarting the app\n• Check internet connection\n• Update app if available\n\nError persists? Call +91 9740 231 041',
      
      // Gratitude
      'thanks': 'You\'re welcome! Need help with anything else? I can assist with fleet, orders, earnings, or KYC queries.',
      'thank you': 'Happy to help! Feel free to ask about vehicles, orders, payments, or any other questions.',
    };
    
    if (responses.containsKey(msg)) {
      return responses[msg]!;
    }
    
    for (final entry in responses.entries) {
      if (msg.contains(entry.key)) {
        return entry.value;
      }
    }
    
    return 'I can help with fleet management, orders, earnings, KYC, and general support. For complex issues, call +91 9740 231 041. What would you like to know?';
  }

  Future<void> _playNotificationSound() async {
    try {
      await SystemSound.play(SystemSoundType.alert);
    } catch (e) {
      print('Error playing sound: $e');
    }
  }

  Future<void> _sendMessage() async {
    final message = _messageController.text.trim();
    if (message.isEmpty || _currentUserId == null || _isSending) return;

    setState(() {
      _isSending = true;
    });

    final userMessage = ChatMessage(
      id: 'user_${DateTime.now().millisecondsSinceEpoch}',
      message: message,
      senderName: _currentUserName!,
      isFromUser: true,
      timestamp: DateTime.now(),
    );

    setState(() {
      _messages.add(userMessage);
    });

    _messageController.clear();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _scrollToBottom();
    });

    Future.delayed(const Duration(milliseconds: 1500), () {
      if (mounted) {
        _simulateAutoReply();
      }
    });

    setState(() {
      _isSending = false;
    });
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundLight,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_rounded, color: AppTheme.primaryBlue),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/vendor');
            }
          },
        ),
        title: const Text(
          'Support Chat',
          style: TextStyle(
            color: AppTheme.primaryBlue,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        centerTitle: true,
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: const EdgeInsets.all(16),
              itemCount: _messages.length,
              itemBuilder: (context, index) {
                final msg = _messages[index];
                return _buildMessageBubble(msg);
              },
            ),
          ),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: AppTheme.borderColor)),
            ),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _messageController,
                    decoration: InputDecoration(
                      hintText: 'Type your message...',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(24),
                        borderSide: const BorderSide(color: AppTheme.borderColor),
                      ),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    ),
                    maxLines: null,
                  ),
                ),
                const SizedBox(width: 8),
                GestureDetector(
                  onTap: _isSending ? null : _sendMessage,
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      gradient: AppTheme.primaryGradient,
                      borderRadius: BorderRadius.circular(24),
                    ),
                    child: Icon(
                      Icons.send_rounded,
                      color: Colors.white,
                      size: _isSending ? 0 : 20,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMessageBubble(ChatMessage msg) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        mainAxisAlignment: msg.isFromUser ? MainAxisAlignment.end : MainAxisAlignment.start,
        children: [
          if (!msg.isFromUser)
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                gradient: AppTheme.primaryGradient,
                borderRadius: BorderRadius.circular(16),
              ),
              child: const Icon(Icons.support_agent_rounded, color: Colors.white, size: 16),
            ),
          const SizedBox(width: 8),
          Flexible(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: msg.isFromUser ? AppTheme.primaryBlue : Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: msg.isFromUser ? null : Border.all(color: AppTheme.borderColor),
              ),
              child: Text(
                msg.message,
                style: TextStyle(
                  color: msg.isFromUser ? Colors.white : AppTheme.textPrimary,
                  fontSize: 13,
                  height: 1.4,
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),
          if (msg.isFromUser)
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                color: AppTheme.primaryBlue,
                borderRadius: BorderRadius.circular(16),
              ),
              child: const Icon(Icons.person_rounded, color: Colors.white, size: 16),
            ),
        ],
      ),
    );
  }
}
