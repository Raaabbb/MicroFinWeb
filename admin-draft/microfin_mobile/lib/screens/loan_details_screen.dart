import 'package:flutter/material.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';
import '../utils/app_dialogs.dart';
import 'loan_application_screen.dart';
import 'payment_gateway_screen.dart';


class LoanDetailsScreen extends StatefulWidget {
  final String? loanNumber;
  const LoanDetailsScreen({super.key, this.loanNumber});

  @override
  State<LoanDetailsScreen> createState() => _LoanDetailsScreenState();
}

class _LoanDetailsScreenState extends State<LoanDetailsScreen> {
  bool _isLoading = true;
  String? _error;
  Map<String, dynamic>? _loan;
  List<dynamic> _schedule = [];

  @override
  void initState() {
    super.initState();
    _fetchDetails();
  }

  Future<void> _fetchDetails() async {
    final loanNum = widget.loanNumber;
    final tenantId = activeTenant.value.id;

    // If no loanNumber provided, try to get the first active loan for the user
    if (loanNum == null) {
      if (currentUser.value == null) {
        if (mounted) setState(() { _isLoading = false; _error = 'Not logged in.'; });
        return;
      }
      try {
        final myLoansUrl = Uri.parse(
          ApiConfig.getUrl('api_get_my_loans.php?user_id=${currentUser.value!['user_id']}&tenant_id=$tenantId&t=${DateTime.now().millisecondsSinceEpoch}')
        );
        final resp = await http.get(myLoansUrl);
        final data = jsonDecode(resp.body);
        if (data['success'] == true && (data['loans'] as List).isNotEmpty) {
          final activeLoans = (data['loans'] as List).where((l) => l['loan_status'] == 'Active').toList();
          if (activeLoans.isEmpty) {
            if (mounted) setState(() { _isLoading = false; _error = 'No active loan found.'; });
            return;
          }
          await _loadLoan(activeLoans.first['loan_number'] as String, tenantId);
        } else {
          if (mounted) setState(() { _isLoading = false; _error = 'No loans found.'; });
        }
      } catch (e) {
        if (mounted) setState(() { _isLoading = false; _error = 'Failed to load loan info.'; });
      }
      return;
    }

    await _loadLoan(loanNum, tenantId);
  }

  Future<void> _loadLoan(String loanNum, String tenantId) async {
    try {
      final url = Uri.parse(
        ApiConfig.getUrl('api_get_loan_details.php?loan_number=$loanNum&tenant_id=$tenantId&t=${DateTime.now().millisecondsSinceEpoch}')
      );
      final resp = await http.get(url);
      final data = jsonDecode(resp.body);
      if (data['success'] == true) {
        if (mounted) {
          setState(() {
            _loan = data['loan'];
            _schedule = data['schedule'] ?? [];
            _isLoading = false;
          });
        }
      } else {
        if (mounted) setState(() { _isLoading = false; _error = data['message'] ?? 'Error loading loan.'; });
      }
    } catch (e) {
      if (mounted) setState(() { _isLoading = false; _error = 'Failed to connect.'; });
    }
  }

  double _getNum(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0.0;

  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.themePrimaryColor;
    final secondary = activeTenant.value.themeSecondaryColor;

    if (_isLoading) {
      return Scaffold(
        backgroundColor: const Color(0xFFF9FAFB),
        body: Center(child: CircularProgressIndicator(color: primary, strokeWidth: 3)),
      );
    }

    if (_error != null || _loan == null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF9FAFB),
        body: Column(
          children: [
            _buildHeader(context, primary),
            Expanded(
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.all(32.0),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.info_outline_rounded, size: 56, color: const Color(0xFF6B7280).withOpacity(0.5)),
                      const SizedBox(height: 16),
                      Text(
                        _error ?? 'No loan details available.',
                        style: const TextStyle(fontSize: 16, color: Color(0xFF6B7280), fontWeight: FontWeight.w500),
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      );
    }

    final loan = _loan!;
    
    // Robust numeric parsing to avoid TypeErrors
    final double progress        = _getNum(loan['progress']);
    final int    progressPerc    = (progress * 100).toInt();
    final int    paymentsMade    = _schedule.where((s) => s['payment_status'] == 'Paid').length;
    final int    totalPayments   = loan['number_of_payments'] is int ? loan['number_of_payments'] : int.tryParse(loan['number_of_payments']?.toString() ?? '') ?? _schedule.length;
    
    // Determine payment amount: Use monthly_amortization but cap at remaining_balance. 
    // Fallback to remaining_balance if monthly_amortization is 0.
    final double monthlyAmort = _getNum(loan['monthly_amortization']);
    final double remainBal    = _getNum(loan['remaining_balance']);
    double suggestedAmount    = monthlyAmort;
    if (suggestedAmount <= 0 || suggestedAmount > remainBal) {
      suggestedAmount = remainBal;
    }
    if (suggestedAmount < 0) suggestedAmount = 0;

    final statusColor = loan['loan_status'] == 'Fully Paid' ? AppColors.primary : loan['loan_status'] == 'Overdue' ? AppColors.secondary : AppColors.primary;

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      body: SafeArea(
        bottom: false,
        child: CustomScrollView(
          physics: const BouncingScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(child: _buildHeader(context, primary)),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 120),
              sliver: SliverList(delegate: SliverChildListDelegate([
              // ── Hero Card ──────────────────────────────────────────
              Container(
                padding: EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: primary,
                  borderRadius: BorderRadius.circular(28),
                  boxShadow: [
                    BoxShadow(
                      color: primary.withOpacity(0.3),
                      blurRadius: 20,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Stack(children: [
                  Positioned(right: -10, top: -10, child: Icon(Icons.account_balance_rounded, size: 120, color: Colors.white.withOpacity(0.06))),
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Text(loan['product_name'] ?? '', style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 13, fontWeight: FontWeight.w600)),
                      _statusBadge(loan['loan_status'] ?? 'Active', statusColor),
                    ]),
                    const SizedBox(height: 6),
                    Text(loan['loan_number'] ?? '', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: -0.3)),
                    const SizedBox(height: 16),
                    Text('₱${_getNum(loan['total_loan_amount']).toStringAsFixed(2)}', style: const TextStyle(color: Colors.white, fontSize: 36, fontWeight: FontWeight.w900, letterSpacing: -1.5, height: 1.0)),
                    const SizedBox(height: 4),
                    Text('Released ${loan['release_date'] ?? ''}', style: TextStyle(color: Colors.white.withOpacity(0.65), fontSize: 13)),
                    const SizedBox(height: 20),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: LinearProgressIndicator(value: progress.clamp(0.0, 1.0), minHeight: 8, backgroundColor: Colors.white.withOpacity(0.2), valueColor: const AlwaysStoppedAnimation<Color>(Colors.white)),
                    ),
                    const SizedBox(height: 10),
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Text('$progressPerc% Paid  •  $paymentsMade of $totalPayments payments', style: TextStyle(color: Colors.white.withOpacity(0.75), fontSize: 12)),
                      Text('₱${_getNum(loan['remaining_balance']).toStringAsFixed(2)} left', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                    ]),

                  ]),
                ]),
              ),
              SizedBox(height: 24),

              // ── Loan Breakdown ─────────────────────────────────────
              _sectionLabel('Loan Breakdown', primary),
              const SizedBox(height: 14),
              Container(
                decoration: BoxDecoration(
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
                child: Column(children: [
                  _detailRow('Principal Amount', '₱${_getNum(loan['principal_amount']).toStringAsFixed(2)}', primary, isFirst: true),
                  _divider(),
                  _detailRow('Interest Rate', '${loan['interest_rate']}% / month', primary),
                  _divider(),
                  _detailRow('Loan Term', '${loan['loan_term_months']} months', primary),
                  _divider(),
                  _detailRow('Monthly Payment', '₱${_getNum(loan['monthly_amortization']).toStringAsFixed(2)}', primary, highlight: true),
                  _divider(),
                  _detailRow('Total Interest', '₱${_getNum(loan['interest_amount']).toStringAsFixed(2)}', primary),
                  _divider(),
                  _detailRow('Total Amount', '₱${_getNum(loan['total_loan_amount']).toStringAsFixed(2)}', primary),
                  _divider(),
                  _detailRow('Remaining Balance', '₱${_getNum(loan['remaining_balance']).toStringAsFixed(2)}', primary, highlight: true),
                  _divider(),
                  _detailRow('Next Due Date', loan['next_payment_due'] ?? 'N/A', AppColors.secondary),
                  _divider(),
                  _detailRow('Release Date', loan['release_date'] ?? 'N/A', primary, isLast: true),
                ]),
              ),
              SizedBox(height: 24),

              // ── Application Information ────────────────────────────
              if (loan['application_data'] != null || loan['purpose_category'] != null || loan['loan_purpose'] != null) ...[
                _sectionLabel('Application Information', primary),
                const SizedBox(height: 14),
                Container(
                  decoration: BoxDecoration(
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
                  child: Column(children: _buildAppDetails(loan, primary)),
                ),
                const SizedBox(height: 24),
              ],

              // ── Stats Row ──────────────────────────────────────────
              Row(children: [
                Expanded(child: _miniStatCard('On-Time', '$paymentsMade/$paymentsMade', AppColors.primary, primary.withOpacity(0.08))),
                const SizedBox(width: 12),
                Expanded(child: _miniStatCard('Overdue', '${loan['days_overdue'] ?? 0}d', AppColors.secondary, AppColors.secondary.withOpacity(0.08))),
                const SizedBox(width: 12),
                Expanded(child: _miniStatCard('Status', loan['loan_status'] ?? 'Active', statusColor, statusColor.withOpacity(0.08))),
              ]),

              const SizedBox(height: 32),

              // ── Payment Schedule ───────────────────────────────────
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                _sectionLabel('Payment Schedule', primary),
                Text('$totalPayments payments', style: TextStyle(fontSize: 13, color: primary, fontWeight: FontWeight.w600)),
              ]),
              const SizedBox(height: 14),
              if (_schedule.isEmpty)
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(color: const Color(0xFFE5E7EB)),
                  ),
                  child: const Center(child: Text('No schedule available yet.', style: TextStyle(color: Color(0xFF6B7280)))),
                )
              else
                Container(
                  decoration: BoxDecoration(
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
                  child: Column(children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                      decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
                      child: const Row(children: [
                        SizedBox(width: 32, child: Text('#', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF6B7280), letterSpacing: 0.5))),
                        Expanded(child: Text('DUE DATE', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF6B7280), letterSpacing: 0.5))),
                        SizedBox(width: 100, child: Text('AMOUNT', textAlign: TextAlign.right, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Color(0xFF6B7280), letterSpacing: 0.5))),
                      ]),
                    ),
                    const Divider(height: 1, color: Color(0xFFE5E7EB)),
                    ..._schedule.take(6).map((s) => _scheduleRow(s, primary)),
                    if (_schedule.length > 6)
                      GestureDetector(
                        onTap: () => _showFullSchedule(context, primary),
                        child: Container(
                          padding: EdgeInsets.symmetric(vertical: 14),
                          decoration: BoxDecoration(borderRadius: BorderRadius.vertical(bottom: Radius.circular(20))),
                          child: Center(child: Text('View all ${_schedule.length} payments →', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: primary))),
                        ),
                      ),
                  ]),
                ),
              SizedBox(height: 28),

              // ── Action Buttons ────────────────────────────────────
              SizedBox(
                width: double.infinity,
                height: 58,
                child: ElevatedButton(
                  onPressed: remainBal > 0 ? () => _showPaymentOptions(context, primary, suggestedAmount) : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: primary,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.payments_outlined, size: 20),
                      const SizedBox(width: 10),
                      Text(remainBal > 0 ? 'Make a Payment' : 'Loan Fully Paid', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 28),
            ])),
          ),
        ],
      ),
    ));
  }

  // ── Helper Widgets ──────────────────────────────────────────────────────────

  Widget _buildHeader(BuildContext context, Color primary) {
    final tenant = activeTenant.value;
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
                  child: const Icon(Icons.arrow_back_rounded, color: Colors.white, size: 24),
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
                    'Loan Details',
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

  Widget _sectionTitle(String t) => Text(t, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF111827), letterSpacing: -0.4));

  Widget _statusBadge(String label, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(color: Colors.white.withOpacity(0.2), borderRadius: BorderRadius.circular(20)),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.circle, color: Colors.white, size: 6),
      const SizedBox(width: 4),
      Text(label.toUpperCase(), style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: 0.8)),
    ]),
  );

  Widget _detailRow(String label, String value, Color primary, {bool highlight = false, bool isFirst = false, bool isLast = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: const TextStyle(fontSize: 14, color: Color(0xFF6B7280), fontWeight: FontWeight.w600)),
        const SizedBox(width: 16),
        Expanded(
          child: Text(value, textAlign: TextAlign.right, style: TextStyle(fontSize: highlight ? 16 : 14, fontWeight: FontWeight.w800, color: highlight ? primary : const Color(0xFF111827))),
        ),
      ]),
    );
  }

  List<Widget> _buildAppDetails(Map<String, dynamic> loan, Color primary) {
    List<Widget> rows = [];
    
    if (loan['purpose_category'] != null && loan['purpose_category'].toString().isNotEmpty) {
      rows.add(_detailRow('Category', loan['purpose_category'].toString(), primary, isFirst: rows.isEmpty));
      rows.add(_divider());
    }
    if (loan['loan_purpose'] != null && loan['loan_purpose'].toString().isNotEmpty) {
      rows.add(_detailRow('Purpose', loan['loan_purpose'].toString(), primary));
      rows.add(_divider());
    }
    
    if (loan['application_data'] != null && loan['application_data'].toString().trim().isNotEmpty) {
      try {
        final parsed = jsonDecode(loan['application_data'].toString()) as Map<String, dynamic>;
        parsed.forEach((k, v) {
          if (v != null && v.toString().trim().isNotEmpty) {
            String formattedKey = k.replaceAll('_', ' ');
            if (formattedKey.isNotEmpty) {
              formattedKey = formattedKey.split(' ').map((w) => w.isNotEmpty ? '${w[0].toUpperCase()}${w.substring(1)}' : '').join(' ');
            }
            rows.add(_detailRow(formattedKey, v.toString(), primary));
            rows.add(_divider());
          }
        });
      } catch (_) {}
    }
    
    if (rows.isNotEmpty) {
      rows.removeLast(); // Remove last divider
    }
    
    if (rows.isEmpty) {
      rows.add(const Padding(padding: EdgeInsets.all(20), child: Text('No application details available', style: TextStyle(color: Color(0xFF6B7280)))));
    }
    return rows;
  }

  Widget _divider() => Divider(height: 1, indent: 18, endIndent: 18, color: AppColors.border);

  Widget _miniStatCard(String label, String value, Color color, Color bg) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20), border: Border.all(color: color.withOpacity(0.1))),
      child: Column(children: [
        Text(value, style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: color, letterSpacing: -0.5), ),
        const SizedBox(height: 4),
        Text(label.toUpperCase(), style: const TextStyle(fontSize: 10, color: Color(0xFF6B7280), fontWeight: FontWeight.w800, letterSpacing: 0.5), ),
      ]),
    );
  }

  Widget _scheduleRow(dynamic s, Color primary) {
    final isPaid = s['payment_status'] == 'Paid';
    return Container(
      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Color(0xFFE5E7EB), width: 1))),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      child: Row(children: [
        SizedBox(width: 32, child: Text('${s['payment_number']}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFF6B7280)))),
        Expanded(child: Text(s['due_date'] ?? '', style: const TextStyle(fontSize: 13, color: Color(0xFF111827), fontWeight: FontWeight.w600))),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (isPaid) ...[
              const Icon(Icons.check_circle_rounded, color: Color(0xFF10B981), size: 14),
              const SizedBox(width: 6),
            ],
            SizedBox(
              width: 80, 
              child: Text(
                '₱${(double.tryParse(s['total_payment']?.toString() ?? '0') ?? 0).toStringAsFixed(2)}', 
                textAlign: TextAlign.right, 
                style: TextStyle(
                  fontSize: 14, 
                  fontWeight: FontWeight.w800, 
                  color: isPaid ? const Color(0xFF10B981) : primary, 
                  letterSpacing: -0.3
                )
              )
            ),
          ],
        ),
      ]),
    );
  }

  void _showFullSchedule(BuildContext context, Color primary) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => DraggableScrollableSheet(
        initialChildSize: 0.85,
        maxChildSize: 0.95,
        minChildSize: 0.5,
        builder: (_, ctrl) => Container(
          decoration: const BoxDecoration(color: Color(0xFFF9FAFB), borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
          child: Column(children: [
            const SizedBox(height: 12),
            Container(width: 40, height: 4, decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2))),
            const SizedBox(height: 16),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                const Text('Full Payment Schedule', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
                IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
              ]),
            ),
            const SizedBox(height: 8),
            Expanded(child: ListView.builder(
              controller: ctrl,
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
              itemCount: _schedule.length,
              itemBuilder: (_, i) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Container(
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFE5E7EB))),
                  child: _scheduleRow(_schedule[i], primary),
                ),
              ),
            )),
          ]),
        ),
      ),
    );
  }

  void _showPaymentOptions(BuildContext context, Color primary, double amount) {
    final double remainBal = _getNum(_loan?['remaining_balance']);
    final double feeVal    = _getNum(_loan?['early_settlement_fee_value']);
    final String feeType   = _loan?['early_settlement_fee_type']?.toString() ?? 'Percentage';
    
    double settlementFee = 0;
    if (feeType == 'Percentage') {
      settlementFee = remainBal * (feeVal / 100);
    } else {
      settlementFee = feeVal;
    }
    
    final double totalSettlement = remainBal + settlementFee;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => Container(
        padding: const EdgeInsets.all(24),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(width: 40, height: 4, decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2))),
            const SizedBox(height: 24),
            const Text('Payment Options', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Color(0xFF111827), letterSpacing: -0.5)),
            const SizedBox(height: 24),
            
            // Option 1: Regular Payment
            _paymentTypeCard(
              context, 
              'Regular Installment', 
              'Pay your scheduled monthly due', 
              amount, 
              Icons.calendar_today_rounded, 
              primary,
              () => _showGatewaySelector(context, primary, amount)
            ),
            
            const SizedBox(height: 16),
            
            // Option 2: Early Settlement
            if (remainBal > 0)
              _paymentTypeCard(
                context, 
                'Early Settlement', 
                'Settle full balance + ${feeVal}${feeType == 'Percentage' ? '%' : ' PHP'} fee', 
                totalSettlement, 
                Icons.rocket_launch_rounded, 
                const Color(0xFF8B5CF6),
                () => _showGatewaySelector(context, primary, totalSettlement, isSettlement: true, fee: settlementFee)
              ),
            
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }

  Widget _paymentTypeCard(BuildContext context, String title, String subtitle, double amount, IconData icon, Color color, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: const Color(0xFFE5E7EB), width: 1.5),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: color.withOpacity(0.1), shape: BoxShape.circle),
              child: Icon(icon, color: color, size: 24),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF111827))),
                  const SizedBox(height: 2),
                  Text(subtitle, style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280), fontWeight: FontWeight.w500)),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(AppFormat.peso(amount), style: TextStyle(fontWeight: FontWeight.w900, color: color, fontSize: 15)),
            const Icon(Icons.chevron_right_rounded, color: Color(0xFFE5E7EB)),
          ],
        ),
      ),
    );
  }

  void _showGatewaySelector(BuildContext context, Color primary, double amount, {bool isSettlement = false, double fee = 0}) {
    Navigator.pop(context); // Close payment options
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => Container(
        padding: const EdgeInsets.all(24),
        decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(32))),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 40, height: 4, decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 24),
          Text(isSettlement ? 'Early Settlement Payment' : 'Select Payment Method', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF111827))),
          const SizedBox(height: 8),
          if (isSettlement)
             Text('Includes ${AppFormat.peso(fee)} settlement fee', style: const TextStyle(color: Color(0xFF6B7280), fontSize: 12, fontWeight: FontWeight.w600)),
          const SizedBox(height: 12),
          Text('Total to pay: ${AppFormat.peso(amount)}', style: TextStyle(color: primary, fontWeight: FontWeight.w800, fontSize: 16)),
          const SizedBox(height: 24),
          _paymentMethodTile(context, 'GCash', 'Pay via GCash Wallet', const Color(0xFF007DFE), Icons.account_balance_wallet_rounded, primary, amount),
          const SizedBox(height: 12),
          _paymentMethodTile(context, 'PayMaya', 'Pay via Maya App', const Color(0xFF00C37B), Icons.credit_card_rounded, primary, amount),
          const SizedBox(height: 12),
          _paymentMethodTile(context, 'Bank Transfer', 'Pay via InstaPay / PESONet', primary, Icons.account_balance_rounded, primary, amount),
          const SizedBox(height: 32),
        ]),
      ),
    );
  }

  Widget _paymentMethodTile(BuildContext context, String title, String subtitle, Color color, IconData icon, Color primary, double amountToPay) {
    return ListTile(
      leading: Container(padding: const EdgeInsets.all(10), decoration: BoxDecoration(color: color.withOpacity(0.1), shape: BoxShape.circle), child: Icon(icon, color: color, size: 20)),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF111827))),
      subtitle: Text(subtitle, style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280), fontWeight: FontWeight.w500)),
      trailing: const Icon(Icons.chevron_right_rounded, color: Color(0xFFE5E7EB)),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20), side: const BorderSide(color: Color(0xFFE5E7EB))),
      onTap: () async {
        Navigator.pop(context); // close bottom sheet
        
        final user = currentUser.value;
        if (user == null) return;

        // Get borrower name
        final firstName = user['first_name']?.toString() ?? '';
        final lastName  = user['last_name']?.toString()  ?? '';
        final fullName  = '$firstName $lastName'.trim().isNotEmpty
            ? '$firstName $lastName'.trim()
            : user['username']?.toString() ?? 'Borrower';

        // Parse IDs safely
        final int loanIdParsed = _loan!['loan_id'] is int 
            ? _loan!['loan_id'] 
            : int.tryParse(_loan!['loan_id'].toString()) ?? 0;
        
        final int userIdParsed = user['user_id'] is int 
            ? user['user_id'] 
            : int.tryParse(user['user_id'].toString()) ?? 0;

        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => PaymentGatewayScreen(
              amount:      amountToPay,
              gatewayName: title,
              loanNumber:  _loan!['loan_number']?.toString() ?? '',
              loanId:      loanIdParsed,
              userId:      userIdParsed,
              tenantId:    activeTenant.value.id,
              borrowerName: fullName,
            ),
          ),
        );

        // Refresh loan data after payment returns
        _fetchDetails();
      },
    );
  }

  // _submitPaymentToApi is now handled inside PaymentGatewayScreen.
  // Kept here for legacy Bank Transfer compatibility.
  Future<void> _submitPaymentToApi(double amount, String method) async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => Center(child: CircularProgressIndicator()),
    );
    try {
      final url  = Uri.parse(ApiConfig.getUrl('api_pay_loan.php'));
      final resp = await http.post(url,
          headers: {'Content-Type': 'application/json'},
          body: jsonEncode({
            'user_id':          currentUser.value!['user_id'],
            'tenant_id':        activeTenant.value.id,
            'loan_id':          _loan!['loan_id'],
            'amount':           amount,
            'payment_method':   method,
            'reference_number': 'REF-${DateTime.now().millisecondsSinceEpoch}',
          }));
      if (mounted) Navigator.pop(context);
      final data = jsonDecode(resp.body);
      if (data['success'] == true && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Payment posted successfully!'), backgroundColor: AppColors.primary),
        );
        _fetchDetails();
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(data['message'] ?? 'Error'), backgroundColor: AppColors.secondary),
        );
      }
    } catch (e) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to connect.'), backgroundColor: AppColors.secondary),
        );
      }
    }
  }
}


