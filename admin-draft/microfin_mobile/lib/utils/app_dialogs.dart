import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
import 'api_config.dart';
import '../main.dart';
import '../screens/live_chat_screen.dart';
import '../screens/client_verification_screen.dart';

class AppDialogs {
  static void showNotifications(BuildContext context, Color primary) {
    showGeneralDialog(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Close',
      barrierColor: Colors.black.withOpacity(0.05),
      transitionDuration: const Duration(milliseconds: 250),
      pageBuilder: (context, anim1, anim2) => Container(),
      transitionBuilder: (context, anim1, anim2, child) {
        return FadeTransition(
          opacity: anim1,
          child: Align(
            alignment: Alignment.topRight,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 80, 20, 20),
              child: Material(
                color: Colors.transparent,
                child: ValueListenableBuilder<List<dynamic>>(
                  valueListenable: globalNotifications,
                  builder: (context, notifs, _) => Container(
                    width: 360,
                    constraints: BoxConstraints(
                      maxHeight: MediaQuery.of(context).size.height * 0.6,
                    ),
                    padding: const EdgeInsets.all(24),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(24),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.12),
                          blurRadius: 30,
                          offset: const Offset(0, 15),
                        ),
                      ],
                      border: Border.all(color: const Color(0xFFE5E7EB)),
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Notifications',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                                color: Color(0xFF111827),
                                letterSpacing: -0.5,
                              ),
                            ),
                            if (notifs.isNotEmpty)
                              GestureDetector(
                                onTap: () async {
                                  globalNotifications.value = [];
                                  Navigator.pop(context);
                                  
                                  try {
                                    final userId = currentUser.value?['user_id'] ?? 0;
                                    final tenantId = activeTenant.value.id;
                                    await http.post(
                                      Uri.parse(ApiConfig.getUrl('api_clear_notifications.php')),
                                      headers: {'Content-Type': 'application/json'},
                                      body: jsonEncode({'user_id': userId, 'tenant_id': tenantId}),
                                    );
                                  } catch (e) {
                                    // Ignore error
                                  }
                                },
                                child: Text(
                                  'Clear All',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: primary,
                                  ),
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 20),
                        if (notifs.isEmpty)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 30),
                            child: Center(
                              child: Column(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.all(16),
                                    decoration: const BoxDecoration(
                                      color: Color(0xFFF3F4F6),
                                      shape: BoxShape.circle,
                                    ),
                                    child: const Icon(
                                      Icons.notifications_off_outlined,
                                      color: Color(0xFF9CA3AF),
                                      size: 24,
                                    ),
                                  ),
                                  const SizedBox(height: 12),
                                  const Text(
                                    'No new notifications',
                                    style: TextStyle(
                                      color: Color(0xFF6B7280),
                                      fontWeight: FontWeight.w600,
                                      fontSize: 13,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          )
                        else
                          Flexible(
                            child: ListView.separated(
                              shrinkWrap: true,
                              physics: const BouncingScrollPhysics(),
                              itemCount: notifs.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 12),
                              itemBuilder: (_, i) {
                                final n = notifs[i];
                                return Container(
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFF9FAFB),
                                    borderRadius: BorderRadius.circular(16),
                                    border: Border.all(color: const Color(0xFFE5E7EB)),
                                  ),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.all(8),
                                        decoration: BoxDecoration(
                                          color: primary.withOpacity(0.1),
                                          shape: BoxShape.circle,
                                        ),
                                        child: Icon(
                                          _getIcon(n['notification_type']),
                                          color: primary,
                                          size: 16,
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              n['title'] ?? 'Notification',
                                              style: const TextStyle(
                                                fontWeight: FontWeight.w800,
                                                fontSize: 13,
                                                color: Color(0xFF111827),
                                              ),
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              n['message'] ?? '',
                                              style: const TextStyle(
                                                fontSize: 12,
                                                color: Color(0xFF6B7280),
                                                height: 1.4,
                                                fontWeight: FontWeight.w500,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  static void showContactSupport(BuildContext context, Color primary) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (_) => Container(
        padding: const EdgeInsets.all(32),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(width: 40, height: 4, decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2))),
            const SizedBox(height: 32),
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(color: primary.withOpacity(0.1), shape: BoxShape.circle),
              child: Icon(Icons.headset_mic_rounded, color: primary, size: 32),
            ),
            const SizedBox(height: 24),
            const Text('Need Assistance?', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: Color(0xFF111827), letterSpacing: -0.5)),
            const SizedBox(height: 8),
            const Text(
              'Our support team is available 24/7 to help you with any issues or queries.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 14, color: Color(0xFF6B7280), height: 1.5, fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 32),
            _supportTile(
              Icons.alternate_email_rounded,
              'Email Support',
              'support@microfin.com',
              primary,
              () => _launchUrl('mailto:support@microfin.com'),
            ),
            const SizedBox(height: 12),
            _supportTile(
              Icons.phone_iphone_rounded,
              'Call Support',
              '+63 917 123 4567',
              primary,
              () => _launchUrl('tel:+639171234567'),
            ),
            const SizedBox(height: 12),
            _supportTile(
              Icons.chat_bubble_outline_rounded,
              'Live Chat',
              'Chat with our team now',
              primary,
              () {
                Navigator.pop(context);
                Navigator.push(context, MaterialPageRoute(builder: (_) => const LiveChatScreen()));
              },
            ),
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }

  static Widget _supportTile(IconData icon, String title, String subtitle, Color primary, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: const Color(0xFFF9FAFB),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: primary.withOpacity(0.1), shape: BoxShape.circle),
              child: Icon(icon, color: primary, size: 18),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF111827))),
                  Text(subtitle, style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280), fontWeight: FontWeight.w500)),
                ],
              ),
            ),
            const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Color(0xFF9CA3AF)),
          ],
        ),
      ),
    );
  }

  static void showVerificationRequired(BuildContext context, Color primary, {String status = 'Unverified'}) {
    IconData icon = Icons.shield_outlined;
    Color iconColor = const Color(0xFF374151);
    String title = 'Verify Now!';
    String sub = 'Please complete your profile verification to unlock loan applications.';
    String btnText = 'Start Verification';
    if (status == 'Approved' || status == 'Verified') return;
    bool isPending = false;

    if (status == 'Pending') {
      icon = Icons.pending_actions_outlined;
      iconColor = const Color(0xFFF59E0B);
      title = 'Documents Under Review';
      sub = 'Your profile is currently being reviewed by our team. Please wait for approval.';
      btnText = 'Under Review';
      isPending = true;
    } else if (status == 'Rejected') {
      icon = Icons.error_outline_rounded;
      iconColor = const Color(0xFFDC2626);
      title = 'Verification Rejected';
      sub = 'Your document verification was rejected. Please resubmit your documents.';
      btnText = 'Resubmit Profile';
    }

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (_) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(40)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 48,
              height: 5,
              decoration: BoxDecoration(
                color: const Color(0xFFE5E7EB),
                borderRadius: BorderRadius.circular(2.5),
              ),
            ),
            const SizedBox(height: 32),
            Container(
              width: 100,
              height: 100,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.06),
                    blurRadius: 20,
                    offset: const Offset(0, 10),
                  ),
                ],
                border: Border.all(color: const Color(0xFFF3F4F6), width: 1.5),
              ),
              child: Center(
                child: Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [
                        const Color(0xFFF9FAFB),
                        const Color(0xFFF3F4F6),
                      ],
                    ),
                  ),
                  child: Icon(icon, color: iconColor, size: 36),
                ),
              ),
            ),
            const SizedBox(height: 32),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.w900,
                color: Color(0xFF111827),
                letterSpacing: -0.8,
              ),
            ),
            const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Text(
                sub,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 15,
                  color: Color(0xFF6B7280),
                  height: 1.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
            const SizedBox(height: 40),
            ElevatedButton(
              onPressed: isPending
                  ? null
                  : () {
                      Navigator.pop(context);
                      importScreens(context, 'verification');
                    },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF374151), // Premium dark button
                foregroundColor: Colors.white,
                minimumSize: const Size(double.infinity, 64),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                elevation: 0,
                disabledBackgroundColor: const Color(0xFFE5E7EB),
              ),
              child: Text(
                btnText,
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, letterSpacing: 0.3),
              ),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  static void importScreens(BuildContext context, String type) {
    if (type == 'verification') {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const ClientVerificationScreen()),
      );
    }
  }

  static Future<void> _launchUrl(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  static IconData _getIcon(String? type) {
    switch (type?.toLowerCase()) {
      case 'payment':
        return Icons.account_balance_wallet_rounded;
      case 'loan':
        return Icons.assignment_turned_in_rounded;
      case 'verification':
        return Icons.verified_user_rounded;
      default:
        return Icons.notifications_active_rounded;
    }
  }
}
