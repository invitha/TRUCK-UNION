import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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

class DriverChatScreen extends StatefulWidget {
  final Map<String, dynamic> driverData;

  const DriverChatScreen({
    super.key,
    required this.driverData,
  });

  @override
  State<DriverChatScreen> createState() => _DriverChatScreenState();
}

class _DriverChatScreenState extends State<DriverChatScreen> {
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  List<ChatMessage> _messages = [];
  bool _isSending = false;
  bool _hasShownWelcome = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _showWelcomeMessage();
    });
  }

  @override
  void dispose() {
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _showWelcomeMessage() async {
    if (_hasShownWelcome) return;
    
    _hasShownWelcome = true;
    await Future.delayed(const Duration(milliseconds: 300));
    
    if (!mounted) return;
    
    final welcomeMessage = ChatMessage(
      id: 'welcome_${DateTime.now().millisecondsSinceEpoch}',
      message: '👋 Hello! Welcome to TRUCK UNION Driver Support!\n\nI can help you with:\n• 📦 Order status & AL numbers\n• 📸 POD photo upload\n• 📋 KYC verification\n• 💰 Earnings & payments\n• 🚚 Delivery instructions\n• 📞 General support\n\nFor urgent matters:\n📞 Call +91 9740 231 041\n\nHow can I assist you today?',
      senderName: 'TRUCK UNION Support',
      isFromUser: false,
      timestamp: DateTime.now(),
    );
    
    setState(() {
      _messages = [welcomeMessage];
    });
    
    _scrollToBottom();
  }

  Future<void> _simulateAutoReply() async {
    if (_messages.isEmpty) return;
    
    final lastMessage = _messages.last;
    if (!lastMessage.isFromUser) return;
    
    await Future.delayed(const Duration(milliseconds: 1500));
    
    if (!mounted) return;
    
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
    
    _scrollToBottom();
  }

  String _generateAutoReply(String message) {
    final msg = message.toLowerCase().trim();
    
    if (msg.contains('order') || msg.contains('status')) {
      return 'To update order status:\n1. Go to "My Orders"\n2. Select the order by AL number\n3. Tap "Update Status"\n4. Choose new status\n5. Upload POD if required\n\nStatus flow: Assigned → Picked Up → In Transit → Delivered';
    }
    
    if (msg.contains('pod') || msg.contains('photo') || msg.contains('picture')) {
      return 'To upload POD (Proof of Delivery):\n1. When updating to "Picked Up" or "Delivered"\n2. System will ask for photo\n3. Take photo using camera\n4. Photo uploads automatically\n\nPOD required for Pickup and Delivery status.';
    }
    
    if (msg.contains('kyc') || msg.contains('verification')) {
      return 'For KYC verification:\n1. Go to "KYC Status" from dashboard\n2. Fill your details\n3. Upload documents:\n   • Aadhar Card (front & back)\n   • PAN Card\n   • Driving License (front & back)\n4. Submit for review\n\nYou\'ll get notification once verified!';
    }
    
    if (msg.contains('al') || msg.contains('number') || msg.contains('tracking')) {
      return 'AL (Airway Bill) numbers:\n• Unique ID for each shipment\n• Use to track orders\n• Update status by AL number\n• View in "My Orders"\n\nEach order has its own AL number.';
    }
    
    if (msg.contains('earning') || msg.contains('payment') || msg.contains('money')) {
      return 'Earnings & Payments:\n• Earn per completed delivery\n• Weekly automatic payouts\n• View earnings in dashboard\n• Track payment history\n\nMore deliveries = More earnings!';
    }
    
    if (msg.contains('help') || msg.contains('support')) {
      return 'I can help with:\n• Order status updates\n• POD photo upload\n• KYC verification\n• AL number queries\n• Earnings & payments\n• General support\n\nFor urgent issues, call +91 9740 231 041';
    }
    
    if (msg.contains('contact') || msg.contains('call') || msg.contains('phone')) {
      return 'Contact Support:\n📞 Phone: +91 9740 231 041\n📧 Email: support@abra-logistic.com\n⏰ Hours: 9 AM - 6 PM (Mon-Sat)\n\nFor urgent issues, please call us directly.';
    }
    
    if (msg.contains('hello') || msg.contains('hi')) {
      return 'Hello! How can I help you today? I can assist with orders, POD uploads, KYC, and more.';
    }
    
    if (msg.contains('thanks') || msg.contains('thank')) {
      return 'You\'re welcome! Need help with anything else? Feel free to ask about orders, KYC, or any other questions.';
    }
    
    return 'I can help with orders, POD uploads, KYC verification, and general support. For complex issues, call +91 9740 231 041. What would you like to know?';
  }

  Future<void> _sendMessage() async {
    final message = _messageController.text.trim();
    if (message.isEmpty || _isSending) return;

    setState(() {
      _isSending = true;
    });

    final driver = widget.driverData['driver'];
    final userMessage = ChatMessage(
      id: 'user_${DateTime.now().millisecondsSinceEpoch}',
      message: message,
      senderName: driver['driver_name'] ?? driver['driverName'] ?? driver['driver_username'] ?? driver['vendor_driver_name'] ?? driver['name'] ?? driver['driver_mobile'] ?? 'Driver',
      isFromUser: true,
      timestamp: DateTime.now(),
    );

    setState(() {
      _messages.add(userMessage);
    });

    _messageController.clear();
    _scrollToBottom();

    await _simulateAutoReply();

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
        title: const Text('Support Chat'),
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
        flexibleSpace: Container(
          decoration: BoxDecoration(gradient: AppTheme.primaryGradient),
        ),
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
                    onSubmitted: (_) => _sendMessage(),
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
                    child: const Icon(
                      Icons.send_rounded,
                      color: Colors.white,
                      size: 20,
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
