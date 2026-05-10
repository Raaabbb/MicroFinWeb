import 'package:flutter/material.dart';
import '../main.dart';
import '../theme.dart';
import '../utils/app_dialogs.dart';
import 'live_chat_screen.dart';

class SupportCenterScreen extends StatefulWidget {
  const SupportCenterScreen({super.key});

  @override
  State<SupportCenterScreen> createState() => _SupportCenterScreenState();
}

class _SupportCenterScreenState extends State<SupportCenterScreen> {
  final _searchCtrl = TextEditingController();

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.themePrimaryColor;

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      body: SafeArea(
        bottom: false,
        child: CustomScrollView(
          physics: const BouncingScrollPhysics(),
          slivers: [
            _buildAppBar(context, primary),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 32, 20, 100),
              sliver: SliverList(
                delegate: SliverChildListDelegate([
                  _sectionHeader('Suggested Topics'),
                  const SizedBox(height: 16),
                  _buildLegitTopicRow(Icons.payments_outlined, 'Payment Methods', 'How to pay your existing loans', primary),
                  _buildLegitTopicRow(Icons.description_outlined, 'Loan Application', 'Process for applying new credit', primary),
                  _buildLegitTopicRow(Icons.security_outlined, 'Security & Login', 'Protecting your account data', primary),
                  _buildLegitTopicRow(Icons.verified_outlined, 'Verification Help', 'Issues with ID uploads', primary),
                  
                  const SizedBox(height: 36),
                  _sectionHeader('Frequently Asked Questions'),
                  const SizedBox(height: 16),
                  _buildFAQItem('How long does loan approval take?', 'Typically between 24-48 hours after verification.', primary),
                  _buildFAQItem('What documents are required?', 'A valid ID, proof of income, and residency are standard.', primary),
                  _buildFAQItem('How do I reset my password?', 'Go to Profile > Settings & Security > Change Password.', primary),
                  _buildFAQItem('Can I have multiple loans?', 'Yes, provided you remain within your credit limit.', primary),
                  
                  const SizedBox(height: 40),
                  _buildContactCard(primary),
                ]),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAppBar(BuildContext context, Color primary) {
    return SliverToBoxAdapter(
      child: Container(
        padding: const EdgeInsets.fromLTRB(24, 20, 24, 40),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: const BorderRadius.vertical(bottom: Radius.circular(40)),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 20, offset: const Offset(0, 4))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                GestureDetector(
                  onTap: () => Navigator.pop(context),
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: const Color(0xFFF3F4F6), borderRadius: BorderRadius.circular(16)),
                    child: const Icon(Icons.close_rounded, size: 20, color: Color(0xFF111827)),
                  ),
                ),
                const Spacer(),
                const Text('Support Center', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
                const Spacer(),
                const SizedBox(width: 44),
              ],
            ),
            const SizedBox(height: 36),
            const Text(
              'How can we help?',
              style: TextStyle(fontSize: 32, fontWeight: FontWeight.w900, color: Color(0xFF111827), height: 1.1, letterSpacing: -1.0),
            ),
            const SizedBox(height: 24),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 4),
              decoration: BoxDecoration(
                color: const Color(0xFFF3F4F6),
                borderRadius: BorderRadius.circular(20),
              ),
              child: TextField(
                controller: _searchCtrl,
                decoration: const InputDecoration(
                  hintText: 'Search for articles...',
                  hintStyle: TextStyle(color: Color(0xFF9CA3AF), fontSize: 16, fontWeight: FontWeight.w500),
                  icon: Icon(Icons.search_rounded, color: Color(0xFF9CA3AF), size: 22),
                  border: InputBorder.none,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _sectionHeader(String title) {
    return Text(
      title,
      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF111827), letterSpacing: -0.5),
    );
  }

  Widget _buildLegitTopicRow(IconData icon, String title, String desc, Color primary) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
            child: Icon(icon, color: primary, size: 22),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
                Text(desc, style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280), fontWeight: FontWeight.w500)),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: Color(0xFF9CA3AF)),
        ],
      ),
    );
  }

  Widget _buildFAQItem(String question, String answer, Color primary) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: ExpansionTile(
        shape: const RoundedRectangleBorder(side: BorderSide.none),
        title: Text(question, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        iconColor: primary,
        children: [
          Text(answer, style: const TextStyle(fontSize: 13, color: Color(0xFF6B7280), height: 1.5, fontWeight: FontWeight.w500)),
        ],
      ),
    );
  }

  Widget _buildContactCard(Color primary) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: primary,
        borderRadius: BorderRadius.circular(28),
        boxShadow: [BoxShadow(color: primary.withOpacity(0.3), blurRadius: 20, offset: const Offset(0, 10))],
      ),
      child: Column(
        children: [
          const Icon(Icons.headset_mic_rounded, color: Colors.white, size: 40),
          const SizedBox(height: 16),
          const Text('Still need help?', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          Text('Talk to Sarah or someone from our team.', 
            style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 13, fontWeight: FontWeight.w500)),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LiveChatScreen())),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: primary,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                elevation: 0,
              ),
              child: const Text('START LIVE CHAT', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 14, letterSpacing: 0.5)),
            ),
          ),
        ],
      ),
    );
  }
}
