import 'package:flutter/material.dart';
import 'dart:async';
import '../main.dart';
import '../theme.dart';

class LiveChatScreen extends StatefulWidget {
  const LiveChatScreen({super.key});

  @override
  State<LiveChatScreen> createState() => _LiveChatScreenState();
}

class _LiveChatScreenState extends State<LiveChatScreen> {
  final _msgCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  bool _isTyping = false;
  
  final List<Map<String, dynamic>> _messages = [
    {
      'isMe': false,
      'text': 'Hello! I am Sarah from Microfin Support. How can I assist you today?',
      'time': '10:30 AM'
    },
  ];

  @override
  void dispose() {
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    Future.delayed(const Duration(milliseconds: 100), () {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  void _sendMessage({String? customText}) {
    final text = (customText ?? _msgCtrl.text).trim();
    if (text.isEmpty) return;

    setState(() {
      _messages.add({
        'isMe': true,
        'text': text,
        'time': _formatTime(DateTime.now()),
      });
      if (customText == null) _msgCtrl.clear();
    });
    _scrollToBottom();

    // Mock Admin Response
    Timer(const Duration(milliseconds: 500), () {
      if (mounted) setState(() => _isTyping = true);
      _scrollToBottom();
      
      Timer(const Duration(seconds: 2), () {
        if (mounted) {
          setState(() {
            _isTyping = false;
            _messages.add({
              'isMe': false,
              'text': _getMockResponse(text),
              'time': _formatTime(DateTime.now()),
            });
          });
          _scrollToBottom();
        }
      });
    });
  }

  String _formatTime(DateTime dt) {
    return '${dt.hour % 12 == 0 ? 12 : dt.hour % 12}:${dt.minute.toString().padLeft(2, '0')} ${dt.hour >= 12 ? 'PM' : 'AM'}';
  }

  String _getMockResponse(String userMsg) {
    userMsg = userMsg.toLowerCase();
    if (userMsg.contains('loan')) return 'Of course! I can check your current loan status. Please provide your loan account number or application ID.';
    if (userMsg.contains('payment') || userMsg.contains('pay')) return 'Our system supports GCash, PayMaya, and Bank Transfers. You can also view your payment schedule in the My Loans section.';
    if (userMsg.contains('hello') || userMsg.contains('hi')) return 'Hi there! I am happy to help you with anything related to your account or loan applications.';
    if (userMsg.contains('thanks') || userMsg.contains('thank')) return 'You are very welcome! Is there anything else I can assist you with?';
    return 'I have forwarded your request to our support specialist. They will get back to you shortly. Can I help with anything else?';
  }

  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.themePrimaryColor;

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      appBar: _buildAppBar(primary),
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              controller: _scrollCtrl,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
              itemCount: _messages.length + (_isTyping ? 1 : 0),
              itemBuilder: (context, i) {
                // If it's the last item and we are typing, show indicator
                if (i == _messages.length) {
                  return _buildTypingIndicator(primary);
                }

                final m = _messages[i];
                final isMe = m['isMe'] as bool;
                
                // Show date chip if it's the first message
                bool showDate = i == 0;

                return Column(
                  children: [
                    if (showDate) _buildDateChip('Today'),
                    _buildMessageBubble(m, isMe, primary),
                  ],
                );
              },
            ),
          ),
          _buildQuickActions(primary),
          _buildInputArea(primary),
        ],
      ),
    );
  }

  PreferredSizeWidget _buildAppBar(Color primary) {
    return AppBar(
      backgroundColor: Colors.white,
      elevation: 0,
      centerTitle: false,
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Color(0xFF1F2937), size: 18),
        onPressed: () => Navigator.pop(context),
      ),
      titleSpacing: 0,
      title: Row(
        children: [
          Stack(
            children: [
              Container(
                width: 40, height: 40,
                decoration: BoxDecoration(
                  gradient: LinearGradient(colors: [primary, primary.withOpacity(0.7)]),
                  shape: BoxShape.circle,
                ),
                child: const Center(child: Icon(Icons.headset_mic_rounded, color: Colors.white, size: 20)),
              ),
              Positioned(
                right: 0, bottom: 0,
                child: Container(
                  width: 12, height: 12,
                  decoration: BoxDecoration(
                    color: const Color(0xFF10B981),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 2),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Sarah Support', style: TextStyle(color: Color(0xFF111827), fontSize: 16, fontWeight: FontWeight.w900, letterSpacing: -0.3)),
              Row(
                children: [
                  Container(width: 6, height: 6, decoration: const BoxDecoration(color: Color(0xFF10B981), shape: BoxShape.circle)),
                  const SizedBox(width: 4),
                  const Text('Online', style: TextStyle(color: Color(0xFF6B7280), fontSize: 12, fontWeight: FontWeight.w600)),
                ],
              ),
            ],
          ),
        ],
      ),
      actions: [
        IconButton(icon: const Icon(Icons.info_outline_rounded, color: Color(0xFF6B7280)), onPressed: () {}),
      ],
      bottom: PreferredSize(
        preferredSize: const Size.fromHeight(1),
        child: Container(color: const Color(0xFFE5E7EB), height: 1),
      ),
    );
  }

  Widget _buildDateChip(String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 24),
      child: Center(
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          decoration: BoxDecoration(
            color: const Color(0xFFE5E7EB),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(text, style: const TextStyle(color: Color(0xFF6B7280), fontSize: 11, fontWeight: FontWeight.w800)),
        ),
      ),
    );
  }

  Widget _buildMessageBubble(Map<String, dynamic> m, bool isMe, Color primary) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Row(
        mainAxisAlignment: isMe ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isMe) ...[
            Container(
              width: 32, height: 32,
              decoration: BoxDecoration(color: const Color(0xFFE5E7EB), shape: BoxShape.circle),
              child: const Center(child: Icon(Icons.headset_mic_rounded, color: Color(0xFF9CA3AF), size: 14)),
            ),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: Column(
              crossAxisAlignment: isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  decoration: BoxDecoration(
                    gradient: isMe ? LinearGradient(
                      colors: [primary, Color.lerp(primary, Colors.black, 0.1)!],
                      begin: Alignment.topLeft, end: Alignment.bottomRight,
                    ) : null,
                    color: isMe ? null : Colors.white,
                    borderRadius: BorderRadius.only(
                      topLeft: const Radius.circular(20),
                      topRight: const Radius.circular(20),
                      bottomLeft: Radius.circular(isMe ? 20 : 4),
                      bottomRight: Radius.circular(isMe ? 4 : 20),
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.04),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Text(
                    m['text'] as String,
                    style: TextStyle(
                      color: isMe ? Colors.white : const Color(0xFF1F2937),
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                      height: 1.4,
                    ),
                  ),
                ),
                const SizedBox(height: 4),
                Text(m['time'] as String, style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 10, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
          if (isMe) const SizedBox(width: 40),
        ],
      ),
    );
  }

  Widget _buildTypingIndicator(Color primary) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 24),
      child: Row(
        children: [
          Container(
            width: 32, height: 32,
            decoration: const BoxDecoration(color: Color(0xFFE5E7EB), shape: BoxShape.circle),
            child: const Center(child: Icon(Icons.headset_mic_rounded, color: Color(0xFF9CA3AF), size: 14)),
          ),
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 10, offset: const Offset(0, 4))],
            ),
            child: Row(
              children: List.generate(3, (i) => Container(
                width: 6, height: 6,
                margin: const EdgeInsets.symmetric(horizontal: 2),
                decoration: BoxDecoration(color: primary.withOpacity(0.3 + (i * 0.2)), shape: BoxShape.circle),
              )),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildQuickActions(Color primary) {
    final actions = ['Loan Details', 'Payment Status', 'Talk to Agent', 'Reset PIN'];
    return Container(
      height: 46,
      margin: const EdgeInsets.only(bottom: 12),
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: actions.length,
        itemBuilder: (context, i) => GestureDetector(
          onTap: () => _sendMessage(customText: actions[i]),
          child: Container(
            margin: const EdgeInsets.only(right: 8),
            padding: const EdgeInsets.symmetric(horizontal: 18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE5E7EB)),
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 8)],
            ),
            child: Center(child: Text(actions[i], 
              style: TextStyle(color: primary, fontSize: 13, fontWeight: FontWeight.w800))),
          ),
        ),
      ),
    );
  }

  Widget _buildInputArea(Color primary) {
    return Container(
      padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.of(context).padding.bottom + 16),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: Color(0xFFE5E7EB))),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: const Color(0xFFF3F4F6), borderRadius: BorderRadius.circular(14)),
            child: const Icon(Icons.image_outlined, color: Color(0xFF9CA3AF), size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                color: const Color(0xFFF3F4F6),
                borderRadius: BorderRadius.circular(18),
              ),
              child: TextField(
                controller: _msgCtrl,
                onSubmitted: (_) => _sendMessage(),
                decoration: const InputDecoration(
                  hintText: 'Ask Sarah something...',
                  hintStyle: TextStyle(color: Color(0xFF9CA3AF), fontSize: 14, fontWeight: FontWeight.w500),
                  border: InputBorder.none,
                ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          GestureDetector(
            onTap: () => _sendMessage(),
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: [primary, Color.lerp(primary, Colors.black, 0.1)!]),
                shape: BoxShape.circle,
                boxShadow: [BoxShadow(color: primary.withOpacity(0.3), blurRadius: 10, offset: const Offset(0, 4))],
              ),
              child: const Icon(Icons.send_rounded, color: Colors.white, size: 20),
            ),
          ),
        ],
      ),
    );
  }
}
