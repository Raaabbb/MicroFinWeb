import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';
import '../utils/app_dialogs.dart';
import 'receipt_screen.dart';

class TransactionHistoryScreen extends StatefulWidget {
  const TransactionHistoryScreen({super.key});

  @override
  State<TransactionHistoryScreen> createState() => _TransactionHistoryScreenState();
}

class _TransactionHistoryScreenState extends State<TransactionHistoryScreen> {
  bool _isLoading = true;
  List<dynamic> _transactions = [];
  String _searchQuery = '';
  String _statusFilter = 'All';
  String _methodFilter = 'All';

  final List<String> _statuses = ['All', 'Paid', 'Posted', 'Cancelled'];
  final List<String> _methods = ['All', 'GCash', 'PayMaya', 'Bank Transfer', 'Cash'];

  @override
  void initState() {
    super.initState();
    _fetchTransactions();
  }

  Future<void> _fetchTransactions() async {
    if (currentUser.value == null) return;
    setState(() => _isLoading = true);
    
    try {
      final user_id = currentUser.value!['user_id'];
      final tenant_id = activeTenant.value.id;
      final url = Uri.parse(ApiConfig.getUrl(
          'api_get_transactions.php?user_id=$user_id&tenant_id=$tenant_id&search=$_searchQuery&status=$_statusFilter&method=$_methodFilter'));
      
      final response = await http.get(url);
      final data = jsonDecode(response.body);
      
      if (data['success'] == true) {
        if (mounted) {
          setState(() {
            _transactions = data['transactions'];
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }
  Widget _buildHeader(BuildContext context, dynamic tenant, Color primary) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              GestureDetector(
                onTap: () => Navigator.pop(context),
                child: Container(
                  width: 48,
                  height: 48,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: Color(0xFF1F2937),
                  ),
                  child:
                      const Icon(Icons.arrow_back_rounded, color: Colors.white, size: 24),
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    tenant.appName,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0F292B),
                      height: 1.1,
                      letterSpacing: -0.3,
                    ),
                  ),
                  const Text(
                    'Payments',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0F292B),
                      height: 1.1,
                      letterSpacing: -0.3,
                    ),
                  ),
                ],
              ),
            ],
          ),
          ValueListenableBuilder<List<dynamic>>(
            valueListenable: globalNotifications,
            builder: (context, notifs, _) => Stack(
              children: [
                IconButton(
                  onPressed: () => AppDialogs.showNotifications(context, primary),
                  icon: const Icon(
                    Icons.notifications_rounded,
                    color: Color(0xFF0F292B),
                    size: 26,
                  ),
                ),
                if (notifs.isNotEmpty)
                  Positioned(
                    top: 10,
                    right: 12,
                    child: Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: Color(0xFFEF4444),
                        shape: BoxShape.circle,
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

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;

    return Scaffold(
      backgroundColor: AppColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          physics: const BouncingScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(child: _buildHeader(context, tenant, primary)),
            SliverToBoxAdapter(child: _buildFilters(primary)),
            _isLoading
                ? const SliverFillRemaining(
                    child: Center(child: CircularProgressIndicator(color: Color(0xFF059669))))
                : _transactions.isEmpty
                    ? SliverFillRemaining(child: _buildEmptyState(primary))
                    : SliverPadding(
                        padding: const EdgeInsets.all(20),
                        sliver: SliverList(
                          delegate: SliverChildBuilderDelegate(
                            (context, index) => _buildTransactionCard(_transactions[index], primary),
                            childCount: _transactions.length,
                          ),
                        ),
                      ),
          ],
        ),
      ),
    );
  }

  Widget _buildFilters(Color primary) {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 20),
      color: Colors.white,
      child: Column(
        children: [
          // Search
          Container(
            decoration: BoxDecoration(
              color: const Color(0xFFF3F4F6),
              borderRadius: BorderRadius.circular(14),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: TextField(
              decoration: const InputDecoration(
                icon: Icon(Icons.search, color: Color(0xFF9CA3AF), size: 20),
                border: InputBorder.none,
                hintText: 'Search ref, loan, or name...',
                hintStyle: TextStyle(color: Color(0xFF9CA3AF), fontSize: 13),
              ),
              onChanged: (val) {
                _searchQuery = val;
                // debounce in a full app, but for now just call
                Future.delayed(const Duration(milliseconds: 500), () => _fetchTransactions());
              },
            ),
          ),
          const SizedBox(height: 12),
          // Filters
          Row(
            children: [
              Expanded(
                child: Container(
                  height: 44,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border.all(color: const Color(0xFFE5E7EB)),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<String>(
                      isExpanded: true,
                      value: _statusFilter,
                      icon: const Icon(Icons.arrow_drop_down, color: Color(0xFF6B7280)),
                      style: const TextStyle(fontSize: 13, color: Color(0xFF111827), fontWeight: FontWeight.w600),
                      onChanged: (v) { setState(() => _statusFilter = v!); _fetchTransactions(); },
                      items: _statuses.map((s) => DropdownMenuItem(value: s, child: Text(s))).toList(),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Container(
                  height: 44,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border.all(color: const Color(0xFFE5E7EB)),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<String>(
                      isExpanded: true,
                      value: _methodFilter,
                      icon: const Icon(Icons.arrow_drop_down, color: Color(0xFF6B7280)),
                      style: const TextStyle(fontSize: 13, color: Color(0xFF111827), fontWeight: FontWeight.w600),
                      onChanged: (v) { setState(() => _methodFilter = v!); _fetchTransactions(); },
                      items: _methods.map((s) => DropdownMenuItem(value: s, child: Text(s))).toList(),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState(Color primary) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: primary.withOpacity(0.05),
              shape: BoxShape.circle,
            ),
            child: Icon(Icons.receipt_long_rounded, size: 48, color: primary.withOpacity(0.4)),
          ),
          const SizedBox(height: 20),
          const Text('No Transactions Found', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
          const SizedBox(height: 8),
          const Text('You don\'t have any payment records yet or\nthey do not match your filters.', textAlign: TextAlign.center, style: TextStyle(fontSize: 13, color: Color(0xFF6B7280))),
        ],
      ),
    );
  }

  Widget _buildTransactionCard(dynamic tx, Color primary) {
    final amount = double.tryParse(tx['payment_amount']?.toString() ?? '0') ?? 0;
    final bal    = double.tryParse(tx['remaining_balance']?.toString() ?? '0') ?? 0;
    final date   = tx['payment_date'] ?? '';
    final ref    = tx['payment_reference'] ?? '';
    final method = tx['payment_method'] ?? '';
    final status = tx['payment_status'] ?? 'Paid';
    
    Color statusColor = const Color(0xFF10B981); // Paid
    if (status == 'Pending' || status == 'Posted') statusColor = const Color(0xFFF59E0B);
    if (status == 'Cancelled' || status == 'Bounced') statusColor = const Color(0xFFEF4444);

    return GestureDetector(
      onTap: () {
        // Open the ReceiptScreen with these details
        Navigator.push(context, MaterialPageRoute(builder: (_) => ReceiptScreen(
          paymentReference: ref,
          amount: amount,
          principalPaid: double.tryParse(tx['principal_paid']?.toString() ?? '0') ?? 0,
          interestPaid: double.tryParse(tx['interest_paid']?.toString() ?? '0') ?? 0,
          paymentMethod: method,
          loanNumber: tx['loan_number'] ?? '',
          borrowerName: tx['client_name'] ?? '',
          paymentDate: date,
          remainingBalance: bal.toStringAsFixed(2),
          status: status,
        )));
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 16),
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: const Color(0xFFE5E7EB)),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 10, offset: const Offset(0, 4))],
        ),
        child: Column(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(color: primary.withOpacity(0.1), shape: BoxShape.circle),
                      child: Icon(Icons.receipt_long_rounded, color: primary, size: 20),
                    ),
                    const SizedBox(width: 12),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Payment Received', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
                        const SizedBox(height: 2),
                        Text(date, style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280), fontWeight: FontWeight.w500)),
                      ],
                    ),
                  ],
                ),
                Text('₱${amount.toStringAsFixed(2)}', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: Color(0xFF111827), letterSpacing: -0.5)),
              ],
            ),
            const SizedBox(height: 16),
            const Divider(height: 1, color: Color(0xFFE5E7EB)),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('REF: $ref', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF6B7280))),
                    const SizedBox(height: 2),
                    Text('via $method', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF111827))),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
                  child: Text(status.toUpperCase(), style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 0.5)),
                )
              ],
            )
          ],
        ),
      ),
    );
  }
}
