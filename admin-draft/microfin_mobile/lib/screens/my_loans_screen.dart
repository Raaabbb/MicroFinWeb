import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';
import '../utils/app_dialogs.dart';
import 'loan_details_screen.dart';

class MyLoansScreen extends StatefulWidget {
  const MyLoansScreen({super.key});

  @override
  State<MyLoansScreen> createState() => _MyLoansScreenState();
}

class _MyLoansScreenState extends State<MyLoansScreen>
    with SingleTickerProviderStateMixin {
  bool _isLoading = true;
  List<dynamic> _loans = [];
  late TabController _tabController;

  double get _totalOutstanding {
    double total = 0;
    for (var l in _loans) {
      if (l['loan_status'] == 'Active') {
        total +=
            double.tryParse(l['remaining_balance']?.toString() ?? '0') ?? 0.0;
      }
    }
    return total;
  }

  int get _activeLoansCount =>
      _loans.where((l) => l['loan_status'] == 'Active').length;

  double get _totalPaid {
    double total = 0;
    for (var l in _loans) {
      total += double.tryParse(l['total_paid']?.toString() ?? '0') ?? 0.0;
    }
    return total;
  }

  @override
  void initState() {
    super.initState();
    activeScreenRefreshTick.addListener(_handleExternalRefresh);
    _tabController = TabController(length: 2, vsync: this);
    _fetchLoans();
  }

  @override
  void dispose() {
    activeScreenRefreshTick.removeListener(_handleExternalRefresh);
    _tabController.dispose();
    super.dispose();
  }

  void _handleExternalRefresh() {
    if (!mounted || currentMainTabIndex.value != 1) return;
    _fetchLoans();
  }

  Future<void> _fetchLoans() async {
    if (currentUser.value == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }
    try {
      final url = Uri.parse(
        ApiConfig.getUrl(
          'api_get_my_loans.php?user_id=${currentUser.value!['user_id']}&tenant_id=${activeTenant.value.id}&t=${DateTime.now().millisecondsSinceEpoch}',
        ),
      );
      final response = await http.get(url);
      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        if (mounted) {
          setState(() {
            _loans = data['loans'] ?? [];
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;

    return Scaffold(
      // ✅ Matches Dashboard backgroundColor
      backgroundColor: const Color(0xFFF9FAFB),
      body: SafeArea(
        bottom: false,
        child: _isLoading
            ? Center(
                child: CircularProgressIndicator(
                  color: primary,
                  strokeWidth: 3,
                ),
              )
            : CustomScrollView(
                // ✅ Matches Dashboard scroll physics
                physics: const BouncingScrollPhysics(),
                slivers: [
                  // ✅ Same SliverToBoxAdapter header pattern as Dashboard
                  SliverToBoxAdapter(
                    child: _buildHeader(context, tenant, primary),
                  ),
                  SliverPadding(
                    // ✅ Same padding as Dashboard SliverPadding
                    padding: const EdgeInsets.fromLTRB(20, 10, 20, 120),
                    sliver: SliverList(
                      delegate: SliverChildListDelegate([
                        _buildStatsCard(primary),
                        const SizedBox(height: 28),
                        _sectionLabel('Active Loans', primary),
                        const SizedBox(height: 14),
                        if (_loans
                            .where((l) => l['loan_status'] == 'Active')
                            .isEmpty)
                          _emptyState(
                            'No active loans.',
                            Icons.account_balance_wallet_outlined,
                            primary,
                          )
                        else
                          ..._loans
                              .where((l) => l['loan_status'] == 'Active')
                              .map(
                                (l) => Padding(
                                  padding: const EdgeInsets.only(bottom: 14),
                                  child: _loanCard(
                                    context,
                                    name: l['product_name'],
                                    loanNo: l['loan_number'],
                                    balance:
                                        double.tryParse(
                                          l['remaining_balance']?.toString() ??
                                              '0',
                                        ) ??
                                        0.0,
                                    nextDue:
                                        double.tryParse(
                                          l['monthly_amortization']
                                                  ?.toString() ??
                                              '0',
                                        ) ??
                                        0.0,
                                    dueDate: l['next_payment_due'] ?? 'N/A',
                                    progress:
                                        double.tryParse(
                                          l['progress']?.toString() ?? '0',
                                        ) ??
                                        0,
                                    status: l['loan_status'],
                                    primary: primary,
                                  ),
                                ),
                              ),
                        const SizedBox(height: 32),
                        _sectionLabel('Loan History', primary),
                        const SizedBox(height: 14),
                        if (_loans
                            .where((l) => l['loan_status'] != 'Active')
                            .isEmpty)
                          _emptyState(
                            'No past loans.',
                            Icons.history_rounded,
                            primary,
                          )
                        else
                          ..._loans
                              .where((l) => l['loan_status'] != 'Active')
                              .map(
                                (l) => Padding(
                                  padding: const EdgeInsets.only(bottom: 14),
                                  child: _loanCard(
                                    context,
                                    name: l['product_name'],
                                    loanNo: l['loan_number'],
                                    balance:
                                        double.tryParse(
                                          l['remaining_balance']?.toString() ??
                                              '0',
                                        ) ??
                                        0.0,
                                    nextDue:
                                        double.tryParse(
                                          l['monthly_amortization']
                                                  ?.toString() ??
                                              '0',
                                        ) ??
                                        0.0,
                                    dueDate: l['next_payment_due'] ?? '—',
                                    progress:
                                        double.tryParse(
                                          l['progress']?.toString() ?? '0',
                                        ) ??
                                        0,
                                    status: l['loan_status'],
                                    primary: primary,
                                  ),
                                ),
                              ),
                      ]),
                    ),
                  ),
                ],
              ),
      ),
    );
  }

  // ============================================================
  // ✅ HEADER — exact copy of Dashboard _buildHeader()
  // Same: avatar circle, tenant name, page name, notification icon area
  // ============================================================
  Widget _buildHeader(BuildContext context, dynamic tenant, Color primary) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              // ✅ Same dark circle avatar as Dashboard
              Container(
                width: 48,
                height: 48,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: Color(0xFF1F2937),
                ),
                child: const Icon(Icons.person, color: Colors.white, size: 28),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ✅ Same tenant name style as Dashboard
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
                  // ✅ Page label replaces username (same font/size/weight)
                  const Text(
                    'My Loans',
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
                  onPressed: () =>
                      AppDialogs.showNotifications(context, primary),
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

  // ============================================================
  // ✅ STATS CARD — same style as Dashboard _buildActivePortfolioCard()
  // Same: primary color bg, border radius 24, white opacity dividers
  // ============================================================
  Widget _buildStatsCard(Color primary) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: primary,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: primary.withOpacity(0.3),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: _statItem(
              Icons.account_balance_wallet_outlined,
              '₱${_totalOutstanding.toStringAsFixed(2)}',
              'Outstanding',
            ),
          ),
          Container(width: 1, height: 48, color: Colors.white.withOpacity(0.2)),
          Expanded(
            child: _statItem(
              Icons.auto_awesome_rounded,
              '$_activeLoansCount',
              'Active Loans',
            ),
          ),
          Container(width: 1, height: 48, color: Colors.white.withOpacity(0.2)),
          Expanded(
            child: _statItem(
              Icons.check_circle_outline_rounded,
              '₱${_totalPaid.toStringAsFixed(2)}',
              'Total Paid',
            ),
          ),
        ],
      ),
    );
  }

  Widget _statItem(IconData icon, String value, String label) {
    return Column(
      children: [
        // ✅ Same white opacity icon style as Dashboard portfolio card labels
        Icon(icon, color: Colors.white.withOpacity(0.8), size: 18),
        const SizedBox(height: 6),
        Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.w900,
            letterSpacing: -0.5,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          label,
          style: TextStyle(
            color: Colors.white.withOpacity(0.7),
            fontSize: 10,
            fontWeight: FontWeight.w600,
            letterSpacing: 0.3,
          ),
        ),
      ],
    );
  }

  // ============================================================
  // ✅ SECTION LABEL — same style as Dashboard "Recent Activity"
  // ============================================================
  Widget _sectionLabel(String title, Color primary) {
    return Row(
      children: [
        Container(
          width: 4,
          height: 20,
          decoration: BoxDecoration(
            color: primary,
            borderRadius: BorderRadius.circular(2),
          ),
        ),
        const SizedBox(width: 10),
        Text(
          title,
          style: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: Color(0xFF111827),
            letterSpacing: -0.5,
          ),
        ),
      ],
    );
  }

  // ============================================================
  // ✅ EMPTY STATE — same white card style as Dashboard action cards
  // ============================================================
  Widget _emptyState(String message, IconData icon, Color primary) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 36, horizontal: 24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              color: primary.withOpacity(0.08),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 30, color: primary.withOpacity(0.4)),
          ),
          const SizedBox(height: 14),
          Text(
            message,
            style: const TextStyle(
              color: Color(0xFF6B7280),
              fontSize: 14,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // ✅ LOAN CARD — same white card, border radius 24, shadow, divider
  // as Dashboard _buildActivePortfolioCard() inner sub-cards
  // ============================================================
  Widget _loanCard(
    BuildContext context, {
    required String name,
    required String loanNo,
    required double balance,
    required double nextDue,
    required String dueDate,
    required double progress,
    required String status,
    required Color primary,
  }) {
    final isPaid = status == 'Fully Paid';
    final isOverdue = status == 'Overdue';

    Color statusColor = primary;
    Color statusBg = primary.withOpacity(0.1);
    if (isPaid) {
      statusColor = const Color(0xFF10B981);
      statusBg = const Color(0xFF10B981).withOpacity(0.1);
    } else if (isOverdue) {
      statusColor = const Color(0xFFEF4444);
      statusBg = const Color(0xFFEF4444).withOpacity(0.1);
    }

    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => LoanDetailsScreen(loanNumber: loanNo),
        ),
      ),
      child: Container(
        decoration: BoxDecoration(
          // ✅ Same white card bg as Dashboard recent activity cards
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: const Color(0xFFE5E7EB)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 20,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: Column(
          children: [
            // ── Card header ──
            Padding(
              padding: const EdgeInsets.all(20),
              child: Row(
                children: [
                  // ✅ Same circle icon container as Dashboard action cards
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: const BoxDecoration(
                      color: Color(0xFFE5E7EB),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.account_balance_wallet_rounded,
                      color: Color(0xFF0F292B),
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF111827),
                            letterSpacing: -0.3,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          loanNo,
                          style: const TextStyle(
                            fontSize: 12,
                            color: Color(0xFF6B7280),
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                  // ✅ Same pill badge style as Dashboard "IN PROGRESS" badge
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: statusBg,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      status.toUpperCase(),
                      style: TextStyle(
                        color: statusColor,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.8,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const Divider(height: 1, color: Color(0xFFE5E7EB)),

            // ── Balance + Progress ──
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _loanStatItem(
                        'BALANCE',
                        '₱${balance.toStringAsFixed(2)}',
                        statusColor,
                      ),
                      _loanStatItem(
                        'MONTHLY',
                        '₱${nextDue.toStringAsFixed(2)}',
                        const Color(0xFF111827),
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'REPAYMENT PROGRESS',
                        style: TextStyle(
                          fontSize: 10,
                          color: Color(0xFF6B7280),
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.5,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: statusColor.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          '${(progress * 100).toInt()}%',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: statusColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  // ✅ Same progress bar style as Dashboard portfolio card
                  ClipRRect(
                    borderRadius: BorderRadius.circular(6),
                    child: LinearProgressIndicator(
                      value: progress,
                      minHeight: 8,
                      backgroundColor: const Color(0xFFE5E7EB),
                      valueColor: AlwaysStoppedAnimation<Color>(statusColor),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      const Icon(
                        Icons.event_outlined,
                        size: 14,
                        color: Color(0xFF6B7280),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        'Next Due: $dueDate',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF6B7280),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            if (!isPaid) ...[
              const Divider(height: 1, color: Color(0xFFE5E7EB)),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
                child: SizedBox(
                  width: double.infinity,
                  height: 52,
                  // ✅ Same button style as Dashboard "Make a Payment" button
                  child: ElevatedButton(
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => LoanDetailsScreen(loanNumber: loanNo),
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: statusColor,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(24),
                      ),
                    ),

                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.payments_outlined, size: 20),
                        SizedBox(width: 10),
                        Text(
                          'PAY NOW',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            letterSpacing: 0.8,
                            fontSize: 15,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _loanStatItem(String label, String value, Color valueColor) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // ✅ Same label style as Dashboard "NEXT PAYMENT", "DUE DATE"
        Text(
          label,
          style: const TextStyle(
            fontSize: 10,
            color: Color(0xFF6B7280),
            fontWeight: FontWeight.w700,
            letterSpacing: 0.8,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w900,
            color: valueColor,
            letterSpacing: -0.4,
          ),
        ),
      ],
    );
  }
}
