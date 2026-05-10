import 'package:flutter/material.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';

class CreditStandingScreen extends StatefulWidget {
  const CreditStandingScreen({super.key});

  @override
  State<CreditStandingScreen> createState() => _CreditStandingScreenState();
}

class _CreditStandingScreenState extends State<CreditStandingScreen> {
  bool _isLoading = true;
  List<dynamic> _history = [];
  Map<String, dynamic>? _latest;

  @override
  void initState() {
    super.initState();
    _fetchHistory();
  }

  Future<void> _fetchHistory() async {
    if (currentUser.value == null) return;
    try {
      final url = Uri.parse(
        ApiConfig.getUrl(
          'api_get_credit_history.php?user_id=${currentUser.value!['user_id']}&tenant_id=${activeTenant.value.id}&t=${DateTime.now().millisecondsSinceEpoch}',
        ),
      );
      final resp = await http.get(url);
      final data = jsonDecode(resp.body);
      if (data['success'] == true && mounted) {
        setState(() {
          _history = data['history'] ?? [];
          if (_history.isNotEmpty) {
            _latest = _history.first;
          }
          _isLoading = false;
        });
      } else {
        setState(() => _isLoading = false);
      }
    } catch (_) {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      appBar: AppBar(
        title: const Text(
          'Credit Standing',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
        ),
        centerTitle: true,
        elevation: 0,
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF111827),
      ),
      body: _isLoading
          ? Center(child: CircularProgressIndicator(color: primary))
          : _history.isEmpty
              ? _buildEmptyState(primary)
              : CustomScrollView(
                  physics: const BouncingScrollPhysics(),
                  slivers: [
                    SliverToBoxAdapter(
                      child: _buildScoreHeader(primary),
                    ),
                    const SliverToBoxAdapter(
                      child: Padding(
                        padding: EdgeInsets.fromLTRB(24, 32, 24, 16),
                        child: Text(
                          'Rating History',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF111827),
                          ),
                        ),
                      ),
                    ),
                    SliverPadding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      sliver: SliverList(
                        delegate: SliverChildBuilderDelegate(
                          (context, index) => _buildHistoryItem(_history[index], primary, index == 0),
                          childCount: _history.length,
                        ),
                      ),
                    ),
                    const SliverToBoxAdapter(child: SizedBox(height: 100)),
                  ],
                ),
    );
  }

  Widget _buildEmptyState(Color primary) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.insights_rounded, size: 80, color: primary.withOpacity(0.2)),
          const SizedBox(height: 24),
          const Text(
            'No History Found',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          const Text(
            'Complete your profile and get verified\nto see your credit standing.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Color(0xFF6B7280)),
          ),
        ],
      ),
    );
  }

  Widget _buildScoreHeader(Color primary) {
    final score = (_latest?['credit_score'] ?? 0) as int;
    final rating = (_latest?['credit_rating'] ?? 'N/A') as String;
    
    // Simple logic for the gauge color
    Color gaugeColor = primary;
    if (rating.contains('Poor') || rating.contains('Standard')) gaugeColor = const Color(0xFFF59E0B);
    if (rating.contains('Excellent') || rating.contains('Premium')) gaugeColor = const Color(0xFF10B981);

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.all(24),
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(32),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        children: [
          Stack(
            alignment: Alignment.center,
            children: [
              SizedBox(
                width: 180,
                height: 180,
                child: CircularProgressIndicator(
                  value: score / 1000,
                  strokeWidth: 12,
                  backgroundColor: const Color(0xFFF3F4F6),
                  color: gaugeColor,
                  strokeCap: StrokeCap.round,
                ),
              ),
              Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    '$score',
                    style: const TextStyle(
                      fontSize: 48,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -2,
                    ),
                  ),
                  const Text(
                    'SCORE',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF9CA3AF),
                      letterSpacing: 2,
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 32),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
            decoration: BoxDecoration(
              color: gaugeColor.withOpacity(0.1),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              rating.toUpperCase(),
              style: TextStyle(
                color: gaugeColor,
                fontSize: 16,
                fontWeight: FontWeight.w900,
                letterSpacing: 1,
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Your current credit standing based on\nour automated evaluation.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              color: Color(0xFF6B7280),
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHistoryItem(dynamic item, Color primary, bool isLatest) {
    final date = DateTime.parse(item['computation_date'] ?? DateTime.now().toIso8601String());
    final score = (item['credit_score'] ?? 0) as int;
    final rating = (item['credit_rating'] ?? 'N/A') as String;
    
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: isLatest ? Colors.white : Colors.white.withOpacity(0.6),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isLatest ? primary.withOpacity(0.2) : const Color(0xFFE5E7EB),
          width: isLatest ? 2 : 1,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: primary.withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(Icons.history_edu_rounded, color: primary, size: 20),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  rating,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                  ),
                ),
                Text(
                  '${date.day}/${date.month}/${date.year}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: Color(0xFF9CA3AF),
                  ),
                ),
              ],
            ),
          ),
          Text(
            '$score',
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w900,
              color: isLatest ? primary : const Color(0xFF111827),
            ),
          ),
        ],
      ),
    );
  }
}
