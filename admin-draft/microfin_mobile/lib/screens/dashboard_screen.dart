import 'dart:convert';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';
import '../utils/app_dialogs.dart';
import 'client_verification_screen.dart';
import 'loan_application_screen.dart';
import 'loan_details_screen.dart';
import 'my_loans_screen.dart';
import 'support_center_screen.dart';
import 'transaction_history_screen.dart';
import 'splash_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen>
    with SingleTickerProviderStateMixin {
  bool _isLoading = true;
  Map<String, dynamic>? _activeLoan;
  List<dynamic> _notifications = [];
  List<dynamic> _featuredProducts = [];
  String _userName = 'User';
  String _clientCode = '';
  bool _isProfileComplete = false;
  String _verificationStatus = 'Unverified';
  double _creditLimit = 0.0;
  late AnimationController _animController;
  late Animation<double> _fadeAnim;
  final PageController _pageController = PageController(viewportFraction: 1.0);
  Timer? _sliderTimer;
  int _currentPage = 0;

  double get _usedCredit {
    if (_activeLoan != null) {
      return double.tryParse(
            _activeLoan!['total_loan_amount']?.toString() ?? '0',
          ) ??
          0.0;
    }
    return 0.0;
  }

  double get _remainingCredit => _creditLimit - _usedCredit;

  @override
  void initState() {
    super.initState();
    activeScreenRefreshTick.addListener(_handleExternalRefresh);
    _animController = AnimationController(
      duration: const Duration(milliseconds: 700),
      vsync: this,
    );
    _fadeAnim = CurvedAnimation(parent: _animController, curve: Curves.easeOut);

    _sliderTimer = Timer.periodic(const Duration(seconds: 4), (Timer timer) {
      if (_featuredProducts.isNotEmpty &&
          _pageController.hasClients &&
          _pageController.positions.length == 1) {
        if (_currentPage < _featuredProducts.length - 1) {
          _currentPage++;
        } else {
          _currentPage = 0;
        }
        _pageController.animateToPage(
          _currentPage,
          duration: const Duration(milliseconds: 600),
          curve: Curves.fastOutSlowIn,
        );
      }
    });

    _fetchDashboard();
  }

  @override
  void dispose() {
    activeScreenRefreshTick.removeListener(_handleExternalRefresh);
    _sliderTimer?.cancel();
    _pageController.dispose();
    _animController.dispose();
    super.dispose();
  }

  void _handleExternalRefresh() {
    if (!mounted || currentMainTabIndex.value != 0) return;
    _fetchDashboard();
  }

  Future<void> _fetchDashboard() async {
    if (currentUser.value == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }
    try {
      final url = Uri.parse(
        ApiConfig.getUrl(
          'api_get_dashboard.php?user_id=${currentUser.value!['user_id']}&tenant_id=${activeTenant.value.id}&t=${DateTime.now().millisecondsSinceEpoch}',
        ),
      );
      final response = await http.get(url);
      // Strip any PHP warnings/notices that may appear before the JSON payload
      String body = response.body;
      final jsonStart = body.indexOf('{');
      if (jsonStart > 0) {
        body = body.substring(jsonStart);
      }
      final data = jsonDecode(body);
      if (data['success'] == true) {
        if (mounted) {
          setState(() {
            _activeLoan = data['active_loan'];
            _notifications = data['notifications'] ?? [];
            globalNotifications.value = _notifications.where((n) {
              final isRead = n['is_read'];
              return isRead == 0 || isRead == '0' || isRead == false;
            }).toList();
            _featuredProducts = data['featured_products'] ?? [];
            _userName = data['user_name'] ?? 'User';
            if (_userName == 'User' && currentUser.value != null) {
              final fname = currentUser.value!['first_name'] ?? '';
              final uname = currentUser.value!['username'] ?? '';
              if (fname.isNotEmpty) {
                _userName = fname;
              } else if (uname.isNotEmpty) {
                _userName = uname;
              }
            }
            _clientCode = data['client_code'] ?? '';
            _isProfileComplete = data['is_profile_complete'] ?? false;
            _verificationStatus = data['verification_status'] ?? 'Unverified';
            currentUser.value?['verification_status'] = _verificationStatus;

            // Safely parse credit limit whether it comes as string or int or double
            final rawLimit = data['credit_limit'];
            _creditLimit = double.tryParse(rawLimit?.toString() ?? '0') ?? 0.0;
            currentUser.value?['credit_limit'] = _creditLimit;

            final policyMeta = data['policy_metadata'];
            if (policyMeta != null) {
               currentUser.value?['policy_metadata'] = policyMeta;
            }

            _isLoading = false;
          });
          _animController.forward();
        }
      } else {
        // Automatically logout if the account was deleted from the server
        if (mounted) {
          currentUser.value = null;
          SharedPreferences.getInstance().then(
            (prefs) => prefs.remove('user_data'),
          );
          setState(() => _isLoading = false);
          Navigator.of(context).pushReplacement(
            MaterialPageRoute(builder: (_) => const SplashScreen()),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        _animController
            .forward(); // Ensure content becomes visible even if some data parsing fails!
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;

    return Scaffold(
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
            : RefreshIndicator(
                color: primary,
                onRefresh: _fetchDashboard,
                child: CustomScrollView(
                  physics: const AlwaysScrollableScrollPhysics(
                    parent: BouncingScrollPhysics(),
                  ),
                  slivers: [
                    SliverToBoxAdapter(child: _buildHeader()),
                    SliverPadding(
                      padding: const EdgeInsets.fromLTRB(20, 10, 20, 120),
                      sliver: SliverList(
                        delegate: SliverChildListDelegate([
                          FadeTransition(
                            opacity: _fadeAnim,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                // Verification banner
                                _buildVerificationBanner(primary),
                                const SizedBox(height: 12),

                                if (_activeLoan != null) ...[
                                  _buildActivePortfolioCard(primary),
                                  const SizedBox(height: 16),
                                  _buildMakePaymentButton(primary),
                                  const SizedBox(height: 24),
                                ] else if (_verificationStatus == 'Approved' &&
                                    _creditLimit > 0) ...[
                                  _buildCreditLimitCard(primary),
                                  const SizedBox(height: 24),
                                ] else ...[
                                  _buildApplyNewCard(primary),
                                  const SizedBox(height: 24),
                                ],
                                Row(
                                  children: [
                                    Expanded(
                                      child: _buildActionCard(
                                        primary,
                                        Icons.history_rounded,
                                        'Loan History',
                                        'Review past activity',
                                        MyLoansScreen(),
                                      ),
                                    ),
                                    const SizedBox(width: 16),
                                    Expanded(
                                      child: _buildActionCard(
                                        primary,
                                        Icons.add_card_rounded,
                                        'Apply New',
                                        'Pre-approved limits',
                                        LoanApplicationScreen(),
                                        isLightBlue: true,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 32),
                                if (_featuredProducts.isNotEmpty) ...[
                                  _buildFeaturedProductsSlider(primary),
                                  const SizedBox(height: 32),
                                ],
                                _buildRecentActivityTitle(),
                                const SizedBox(height: 16),
                                _buildRecentActivityList(primary),
                                const SizedBox(height: 24),
                                _buildNeedAssistanceCard(primary),
                              ],
                            ),
                          ),
                        ]),
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }

  String _formatDateShort(String? dateStr) {
    if (dateStr == null) return '';
    try {
      final date = DateTime.parse(dateStr);
      final months = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
      ];
      return '${months[date.month - 1]} ${date.day}, ${date.year}';
    } catch (_) {
      return dateStr;
    }
  }

  Widget _buildHeader() {
    final tenant = activeTenant.value;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
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
                  Text(
                    _userName.split(' ').first.isNotEmpty
                        ? _userName.split(' ').first
                        : '',
                    style: const TextStyle(
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
                  onPressed: () => AppDialogs.showNotifications(
                    context,
                    tenant.themePrimaryColor,
                  ),
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

  Widget _buildActivePortfolioCard(Color primary) {
    final loan = _activeLoan!;
    double getNum(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0.0;

    final progress = getNum(loan['progress']);
    final remaining =
        '₱${getNum(loan['remaining_balance']).toStringAsFixed(2)}';
    final total = '₱${getNum(loan['total_loan_amount']).toStringAsFixed(2)}';
    final paid = '₱${getNum(loan['total_paid']).toStringAsFixed(2)}';
    final nextAmt =
        '₱${getNum(loan['monthly_amortization']).toStringAsFixed(2)}';
    final dueDate = loan['next_payment_due'] ?? 'N/A';

    // Convert dueDate like "2024-10-24" -> "Oct 24"
    String shortDueDate = dueDate;
    try {
      if (dueDate != 'N/A') {
        final d = DateTime.parse(dueDate);
        final m = [
          'Jan',
          'Feb',
          'Mar',
          'Apr',
          'May',
          'Jun',
          'Jul',
          'Aug',
          'Sep',
          'Oct',
          'Nov',
          'Dec',
        ];
        shortDueDate = '${m[d.month - 1]} ${d.day}';
      }
    } catch (_) {}

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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'ACTIVE PORTFOLIO',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  color: Colors.white.withOpacity(0.8),
                  letterSpacing: 1.0,
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Text(
                  'IN PROGRESS',
                  style: TextStyle(
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                    color: Colors.white,
                    letterSpacing: 0.8,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            loan['product_name'] ?? 'Personal Loan',
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w800,
              color: Colors.white,
              letterSpacing: -1.0,
            ),
          ),
          const SizedBox(height: 24),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                'Remaining Balance',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Colors.white.withOpacity(0.8),
                ),
              ),
              Text(
                remaining,
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: Colors.white,
                  letterSpacing: -0.5,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 8,
              backgroundColor: Colors.white.withOpacity(0.2),
              valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
            ),
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'PAID: $paid',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  color: Colors.white.withOpacity(0.8),
                  letterSpacing: 0.5,
                ),
              ),
              Text(
                'TOTAL: $total',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  color: Colors.white.withOpacity(0.8),
                  letterSpacing: 0.5,
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'NEXT PAYMENT',
                        style: TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                          color: Colors.white.withOpacity(0.8),
                          letterSpacing: 0.8,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        nextAmt,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'DUE DATE',
                        style: TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                          color: Colors.white.withOpacity(0.8),
                          letterSpacing: 0.8,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        shortDueDate,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMakePaymentButton(Color primary) {
    return ElevatedButton(
      onPressed: () async {
        await Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => LoanDetailsScreen()),
        );
        _fetchDashboard();
      },
      style: ElevatedButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(vertical: 20),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        elevation: 0,
      ),
      child: const Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.payments_outlined, size: 20),
          SizedBox(width: 10),
          Text(
            'Make a Payment',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }

  Widget _buildCreditLimitCard(Color primary) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF111827), Color(0xFF1F2937)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF111827).withOpacity(0.3),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'VERIFIED CREDIT LIMIT',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  color: Colors.white.withOpacity(0.7),
                  letterSpacing: 1.0,
                ),
              ),
              Icon(Icons.verified, color: primary, size: 16),
            ],
          ),
          const SizedBox(height: 8),
          const Text(
            'Ready to Apply',
            style: TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w800,
              color: Colors.white,
              letterSpacing: -1.0,
            ),
          ),
          const SizedBox(height: 24),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Total Limit',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: Colors.white.withOpacity(0.7),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '₱${_creditLimit.toStringAsFixed(2)}',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                    ),
                  ),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    'Available',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: Colors.white.withOpacity(0.7),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '₱${_remainingCredit.toStringAsFixed(2)}',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: primary,
                      letterSpacing: -0.5,
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: _buildApplyNewButton(primary, isDarkCard: true),
          ),
        ],
      ),
    );
  }

  Widget _buildApplyNewButton(Color primary, {bool isDarkCard = false}) {
    return ElevatedButton(
      onPressed: () async {
        if (_verificationStatus != 'Approved' &&
            _verificationStatus != 'Verified') {
          AppDialogs.showVerificationRequired(
            context,
            primary,
            status: _verificationStatus,
          );
        } else {
          await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const LoanApplicationScreen()),
          );
          _fetchDashboard();
        }
      },
      style: ElevatedButton.styleFrom(
        backgroundColor: isDarkCard ? primary : primary,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(vertical: 16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        elevation: 0,
      ),
      child: const Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.add_circle_outline, size: 20),
          SizedBox(width: 8),
          Text(
            'Apply Make a Loan',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }

  Widget _buildApplyNewCard(Color primary) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Icon(Icons.add_card_rounded, size: 48, color: primary),
          const SizedBox(height: 16),
          const Text(
            'No Active Loans',
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: Color(0xFF111827),
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Apply for a loan today to get started with your financial goals.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Color(0xFF6B7280), fontSize: 13),
          ),
          const SizedBox(height: 20),
          _buildApplyNewButton(primary),
        ],
      ),
    );
  }

  Widget _buildActionCard(
    Color primary,
    IconData icon,
    String title,
    String subtitle,
    Widget screen, {
    bool isLightBlue = false,
  }) {
    // If not approved, show lock on Application
    bool isLocked =
        title == 'Apply New' &&
        (_verificationStatus != 'Approved' &&
            _verificationStatus != 'Verified');

    return GestureDetector(
      onTap: () async {
        if (isLocked) {
          AppDialogs.showVerificationRequired(
            context,
            primary,
            status: _verificationStatus,
          );
        } else {
          await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => screen),
          );
          _fetchDashboard();
        }
      },
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: isLightBlue
              ? const Color(0xFFD6EAF3)
              : const Color(0xFFE5E7EB),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: const BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
              ),
              child: Icon(
                isLocked ? Icons.lock_outline : icon,
                color: const Color(0xFF0F292B),
                size: 20,
              ),
            ),
            const SizedBox(height: 24),
            Text(
              title,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: Color(0xFF111827),
                letterSpacing: -0.3,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              subtitle,
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w500,
                color: Color(0xFF6B7280),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRecentActivityTitle() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        const Text(
          'Recent Activity',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: Color(0xFF111827),
            letterSpacing: -0.5,
          ),
        ),
        GestureDetector(
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => const TransactionHistoryScreen(),
              ),
            );
          },
          child: const Text(
            'View All',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Color(0xFF0F292B), // primary dark green
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildRecentActivityList(Color primary) {
    if (_notifications.isEmpty) {
      return Container(
        padding: const EdgeInsets.symmetric(vertical: 24),
        alignment: Alignment.center,
        child: const Text(
          'No recent activity',
          style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 13),
        ),
      );
    }

    return Column(
      children: _notifications.take(3).map((n) {
        bool isPayment =
            n['notification_type'] == 'Payment Received' ||
            n['notification_type'] == 'Payment';
        String amountText = '';
        if (isPayment && n['message'].toString().contains('paid')) {
          // crude extraction of amount for visual similarity
          final match = RegExp(
            r'[\u20b1$P]?\s?(\d[\d,]*(?:\.\d+)?)',
          ).firstMatch(n['message']);
          if (match != null) {
            amountText = '-\$${match.group(1)}';
          }
        }

        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors
                .white, // In screenshot it's very light grey, we use white or F3F4F6
            borderRadius: BorderRadius.circular(16),
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: const BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Icon(
                    isPayment ? Icons.check_circle : Icons.notifications,
                    color: const Color(0xFF0F292B), // Dark green circle
                    size: 20,
                  ),
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      n['title'] ?? 'Notification',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF111827),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _formatDateShort(n['created_at']),
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFF6B7280),
                      ),
                    ),
                  ],
                ),
              ),
              if (amountText.isNotEmpty)
                Text(
                  amountText,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF111827),
                  ),
                ),
            ],
          ),
        );
      }).toList(),
    );
  }

  Widget _buildNeedAssistanceCard(Color primary) {
    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const SupportCenterScreen()),
      ),
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: primary,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Need Assistance?',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Your architect is ready to help.',
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.white.withOpacity(0.7),
                  ),
                ),
              ],
            ),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.15),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.chat_bubble_outline_rounded,
                color: Colors.white,
                size: 20,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildVerificationBanner(Color primary) {
    if (_verificationStatus == 'Approved' || _verificationStatus == 'Verified')
      return const SizedBox.shrink();

    String title = 'Verify Identity';
    Color bgColor = const Color(0xFFFEF3C7);
    Color textColor = const Color(0xFF92400E);

    if (_verificationStatus == 'Pending') {
      title = 'Under Review';
    } else if (_verificationStatus == 'Rejected') {
      title = 'Verification Rejected';
      bgColor = const Color(0xFFFEE2E2);
      textColor = const Color(0xFFB91C1C);
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Icon(Icons.shield_outlined, color: textColor, size: 24),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: textColor,
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                    ),
                  ),
                  Text(
                    'Complete profile to unlock loans',
                    style: TextStyle(
                      color: textColor.withOpacity(0.8),
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ],
          ),
          if (_verificationStatus != 'Pending')
            ElevatedButton(
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => ClientVerificationScreen()),
                ).then((_) => _fetchDashboard());
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: textColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                minimumSize: const Size(0, 36),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              child: const Text(
                'Go',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildFeaturedProductsSlider(Color primary) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Featured for You',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF111827),
                    letterSpacing: -0.5,
                  ),
                ),
                Text(
                  'Exclusive loan offers',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: const Color(0xFF6B7280).withOpacity(0.8),
                  ),
                ),
              ],
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                '${_currentPage + 1}/${_featuredProducts.length}',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                  color: primary,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        SizedBox(
          height: 180, // Taller for premium feel
          child: PageView.builder(
            controller: _pageController,
            itemCount: _featuredProducts.length,
            onPageChanged: (index) {
              setState(() => _currentPage = index);
            },
            itemBuilder: (context, index) {
              final product = _featuredProducts[index];
              final String name = product['product_name'] ?? 'Product';
              final double amount =
                  double.tryParse(product['max_amount']?.toString() ?? '0') ??
                  0.0;
              final double rate =
                  double.tryParse(
                    product['interest_rate']?.toString() ?? '0',
                  ) ??
                  0.0;

              return Container(
                margin: EdgeInsets.zero,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFF6366F1).withOpacity(0.25),
                      blurRadius: 15,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(24),
                  child: Stack(
                    children: [
                      Container(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [primary, primary.withOpacity(0.8)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                        ),
                      ),
                      // Decorative large circles for sleek design
                      Positioned(
                        right: -20,
                        top: -20,
                        child: Container(
                          width: 100,
                          height: 100,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: Colors.white.withOpacity(0.1),
                          ),
                        ),
                      ),
                      Positioned(
                        left: -30,
                        bottom: -30,
                        child: Container(
                          width: 80,
                          height: 80,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: Colors.white.withOpacity(0.05),
                          ),
                        ),
                      ),
                      // Content
                      Padding(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(8),
                                  decoration: BoxDecoration(
                                    color: Colors.white.withOpacity(0.2),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: const Icon(
                                    Icons.rocket_launch_rounded,
                                    color: Colors.white,
                                    size: 24,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        name,
                                        style: const TextStyle(
                                          fontSize: 18,
                                          fontWeight: FontWeight.w800,
                                          color: Colors.white,
                                          letterSpacing: -0.5,
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      Container(
                                        margin: const EdgeInsets.only(
                                          top: 4,
                                        ), // ✅ correct
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 8,
                                          vertical: 2,
                                        ),
                                        decoration: BoxDecoration(
                                          color: Colors.black.withOpacity(0.15),
                                          borderRadius: BorderRadius.circular(
                                            6,
                                          ),
                                        ),
                                        child: Text(
                                          '${rate.toStringAsFixed(1)}% Interest',
                                          style: const TextStyle(
                                            fontSize: 10,
                                            fontWeight: FontWeight.w700,
                                            color: Colors.white,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Eligible Up To',
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600,
                                        color: Colors.white.withOpacity(0.8),
                                      ),
                                    ),
                                    Text(
                                      '₱${amount.toStringAsFixed(2)}',
                                      style: const TextStyle(
                                        fontSize: 22,
                                        fontWeight: FontWeight.w800,
                                        color: Colors.white,
                                        letterSpacing: -0.5,
                                      ),
                                    ),
                                  ],
                                ),
                                GestureDetector(
                                  onTap: () async {
                                    if (_verificationStatus != 'Approved' &&
                                        _verificationStatus != 'Verified') {
                                      AppDialogs.showVerificationRequired(
                                        context,
                                        primary,
                                        status: _verificationStatus,
                                      );
                                    } else {
                                      await Navigator.push(
                                        context,
                                        MaterialPageRoute(
                                          builder: (_) =>
                                              const LoanApplicationScreen(),
                                        ),
                                      );
                                      _fetchDashboard();
                                    }
                                  },
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 14,
                                      vertical: 8,
                                    ),
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(12),
                                      boxShadow: [
                                        BoxShadow(
                                          color: Colors.black.withOpacity(0.1),
                                          blurRadius: 4,
                                          offset: const Offset(0, 2),
                                        ),
                                      ],
                                    ),
                                    child: Text(
                                      'Apply',
                                      style: TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w800,
                                        color: primary,
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
