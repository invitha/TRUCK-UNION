import 'package:flutter/material.dart';
import '../../config/app_theme.dart';

class DriverFAQScreen extends StatelessWidget {
  const DriverFAQScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundLight,
      appBar: AppBar(
        title: const Text('FAQs'),
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
        flexibleSpace: Container(
          decoration: BoxDecoration(gradient: AppTheme.primaryGradient),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: AppTheme.primaryGradient,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(
                children: [
                  const Icon(Icons.help_outline_rounded, size: 48, color: Colors.white),
                  const SizedBox(height: 12),
                  const Text(
                    'Frequently Asked Questions',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Find answers to common questions',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.9),
                      fontSize: 14,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            _buildFAQCard(
              question: 'How do I update order status?',
              answer: 'Go to "My Orders" tab, select the order by AL number, tap "Update Status", choose the new status, and upload POD photo if required.\n\nStatus flow: Assigned → Picked Up → In Transit → Delivered',
            ),
            _buildFAQCard(
              question: 'What is POD photo?',
              answer: 'POD (Proof of Delivery) is a photo you take when picking up or delivering an order. It serves as proof of the transaction. POD is required for both Pickup and Delivery status updates.',
            ),
            _buildFAQCard(
              question: 'How do I complete KYC?',
              answer: 'Go to "Profile" → "KYC Verification", fill your details, and upload required documents:\n• Aadhar Card (front & back)\n• PAN Card\n• Driving License (front & back)\n\nYou\'ll get notification once verified.',
            ),
            _buildFAQCard(
              question: 'What are AL numbers?',
              answer: 'AL (Airway Bill) numbers are unique identifiers for each shipment. You use them to track and update order status. Each order has its own AL number.',
            ),
            _buildFAQCard(
              question: 'How do I get paid?',
              answer: 'Your payment is handled by your vendor (the company that assigned you the order). After you complete a delivery, your vendor will review the proof of delivery and clear your payment.\n\nFor payment queries, contact your vendor directly or reach out to support.',
            ),
            _buildFAQCard(
              question: 'What if I face an issue?',
              answer: 'For any issues:\n• Use Live Chat in Support\n• Call +91 9740 231 041\n• WhatsApp us\n• Email support@abra-logistic.com\n\nOur team is available 9 AM - 6 PM (Mon-Sat)',
            ),
            _buildFAQCard(
              question: 'How do I track my orders?',
              answer: 'All your assigned orders appear in "My Orders" tab. You can see order details, status, pickup/delivery locations, and update status from there.',
            ),
            _buildFAQCard(
              question: 'Can I reject an order?',
              answer: 'Yes — but only for orders that have been assigned to you and not yet picked up.\n\nGo to "My Orders", open the assigned order, and tap "Reject Assignment". Once you have picked up a shipment, it cannot be rejected.\n\nIf you face any issues rejecting, contact support at +91 9740 231 041.',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFAQCard({
    required String question,
    required String answer,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Theme(
        data: ThemeData(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          title: Text(
            question,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: AppTheme.primaryBlue,
            ),
          ),
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: Text(
                answer,
                style: TextStyle(
                  fontSize: 14,
                  color: Colors.grey[700],
                  height: 1.5,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
