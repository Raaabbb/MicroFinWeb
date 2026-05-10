import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';
import 'receipt_screen.dart';
import 'package:url_launcher/url_launcher.dart';

class PaymentResult {
  final bool success;
  final String? paymentReference;
  final double? amount;
  final double? principalPaid;
  final double? interestPaid;
  final String? paymentMethod;
  final String? remainingBalance;

  PaymentResult({
    required this.success,
    this.paymentReference,
    this.amount,
    this.principalPaid,
    this.interestPaid,
    this.paymentMethod,
    this.remainingBalance,
  });
}

class PaymentGatewayScreen extends StatefulWidget {
  final double amount;
  final String gatewayName;
  final String loanNumber;
  final int loanId;
  final int userId;
  final String tenantId;
  final String borrowerName;

  const PaymentGatewayScreen({
    super.key,
    required this.amount,
    required this.gatewayName,
    required this.loanNumber,
    required this.loanId,
    required this.userId,
    required this.tenantId,
    required this.borrowerName,
  });

  @override
  State<PaymentGatewayScreen> createState() => _PaymentGatewayScreenState();
}

class _PaymentGatewayScreenState extends State<PaymentGatewayScreen>
    with TickerProviderStateMixin {
  _GatewayStep _step = _GatewayStep.creating;
  String? _errorMessage;
  String? _sourceId;
  String? _checkoutUrl;

  Timer? _pollTimer;
  int _pollCount = 0;
  static const int _maxPolls = 60;

  late AnimationController _pulseCtrl;
  late Animation<double> _pulseAnim;

  Color get _gatewayColor {
    switch (widget.gatewayName) {
      case 'GCash':   return const Color(0xFF007DFE);
      case 'Maya':
      case 'PayMaya': return const Color(0xFF00C37B);
      default:        return activeTenant.value.themePrimaryColor;
    }
  }

  IconData get _gatewayIcon {
    switch (widget.gatewayName) {
      case 'GCash':   return Icons.account_balance_wallet_rounded;
      case 'Maya':
      case 'PayMaya': return Icons.credit_card_rounded;
      default:        return Icons.account_balance_rounded;
    }
  }

  bool get _usesPaymongo =>
      widget.gatewayName == 'GCash' ||
      widget.gatewayName == 'PayMaya' ||
      widget.gatewayName == 'Maya';

  @override
  void initState() {
    super.initState();
    _pulseCtrl = AnimationController(vsync: this, duration: const Duration(seconds: 1))
      ..repeat(reverse: true);
    _pulseAnim = Tween<double>(begin: 0.85, end: 1.0)
        .animate(CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut));

    if (_usesPaymongo) {
      _initPaymongo();
    } else {
      _processDirectPayment();
    }
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _pulseCtrl.dispose();
    super.dispose();
  }

  Future<void> _initPaymongo() async {
    setState(() => _step = _GatewayStep.creating);
    try {
      final url  = Uri.parse(ApiConfig.getUrl('api_paymongo_create_source.php'));
      final resp = await http.post(url,
          headers: {'Content-Type': 'application/json'},
          body: jsonEncode({
            'user_id':        widget.userId,
            'tenant_id':      widget.tenantId,
            'loan_id':        widget.loanId,
            'amount':         widget.amount,
            'payment_method': widget.gatewayName,
          }));
      final data = jsonDecode(resp.body);
      if (data['success'] == true) {
        _sourceId    = data['source_id'];
        _checkoutUrl = data['checkout_url'];
        setState(() => _step = _GatewayStep.waitingUser);
        _launchExternalUrl(_checkoutUrl!);
        _startPolling();
      } else {
        setState(() {
          _step         = _GatewayStep.failed;
          _errorMessage = data['message'] ?? 'Failed to initialize payment.';
        });
      }
    } catch (e) {
      setState(() {
        _step         = _GatewayStep.failed;
        _errorMessage = 'Connection error. Please try again.';
      });
    }
  }

  Future<void> _launchExternalUrl(String urlString) async {
    try {
      final uri = Uri.parse(urlString);
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        debugPrint('Could not launch $urlString');
      }
    } catch (e) {
      debugPrint('Error launching URL: $e');
    }
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) async {
      _pollCount++;
      if (_pollCount > _maxPolls) {
        _pollTimer?.cancel();
        if (mounted) {
          setState(() {
            _step         = _GatewayStep.failed;
            _errorMessage = 'Payment timed out. Please try again.';
          });
        }
        return;
      }
      await _checkPaymentStatus();
    });
  }

  Future<void> _checkPaymentStatus() async {
    if (_sourceId == null) return;
    try {
      final url  = Uri.parse(ApiConfig.getUrl('api_paymongo_check_status.php'));
      final resp = await http.post(url,
          headers: {'Content-Type': 'application/json'},
          body: jsonEncode({'source_id': _sourceId, 'loan_id': widget.loanId}));
      final data = jsonDecode(resp.body);
      if (data['success'] == true) {
        final status = data['status'] ?? 'pending';
        if (status == 'completed') {
          _pollTimer?.cancel();
          if (mounted) {
            setState(() => _step = _GatewayStep.success);
            await _submitPaymentAndShowReceipt();
          }
        } else if (status == 'failed') {
          _pollTimer?.cancel();
          if (mounted) {
            setState(() {
              _step         = _GatewayStep.failed;
              _errorMessage = 'Payment was declined or cancelled.';
            });
          }
        }
      }
    } catch (_) {}
  }

  Future<void> _processDirectPayment() async {
    setState(() => _step = _GatewayStep.creating);
    await Future.delayed(const Duration(seconds: 2));
    await _submitPaymentAndShowReceipt();
  }

  Future<void> _submitPaymentAndShowReceipt() async {
    setState(() => _step = _GatewayStep.processing);
    try {
      final url  = Uri.parse(ApiConfig.getUrl('api_pay_loan.php'));
      final ref  = 'REF-${DateTime.now().millisecondsSinceEpoch}';
      final resp = await http.post(url,
          headers: {'Content-Type': 'application/json'},
          body: jsonEncode({
            'user_id':          widget.userId,
            'tenant_id':        widget.tenantId,
            'loan_id':          widget.loanId,
            'amount':           widget.amount,
            'payment_method':   widget.gatewayName,
            'reference_number': _sourceId ?? ref,
          }));
      final data = jsonDecode(resp.body);
      if (data['success'] == true && mounted) {
        final payRef = data['payment_reference'] ?? _sourceId ?? ref;
        
        // Trigger background email sending
        if (data['client_email'] != null && data['client_email'].toString().isNotEmpty) {
          try {
            http.post(
              Uri.parse(ApiConfig.getUrl('api_send_receipt_email.php')),
              headers: {'Content-Type': 'application/json'},
              body: jsonEncode({
                'payment_reference': payRef,
                'client_email': data['client_email'],
                'client_name': data['client_name'],
                'amount': widget.amount,
                'payment_date': data['payment_date'],
                'payment_method': widget.gatewayName,
                'loan_number': data['loan_number'],
                'tenant_name': activeTenant.value.appName,
                'tenant_id': widget.tenantId,
              }),
            );
          } catch (_) {}
        }

        final now    = DateTime.now();
        final months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        final dateStr = '${months[now.month - 1]} ${now.day}, ${now.year}';
        final p = widget.amount * 0.85;
        final i = widget.amount * 0.15;
        if (mounted) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(
              builder: (_) => ReceiptScreen(
                paymentReference: payRef,
                amount:           widget.amount,
                principalPaid:    p,
                interestPaid:     i,
                paymentMethod:    widget.gatewayName,
                loanNumber:       widget.loanNumber,
                borrowerName:     widget.borrowerName,
                paymentDate:      dateStr,
                status:           'Paid',
              ),
            ),
          );
        }
      } else {
        if (mounted) {
          setState(() {
            _step         = _GatewayStep.failed;
            _errorMessage = data['message'] ?? 'Payment posting failed.';
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _step         = _GatewayStep.failed;
          _errorMessage = 'Failed to connect to server.';
        });
      }
    }
  }

  // ── BUILD ──────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.themePrimaryColor;
    return Scaffold(
      backgroundColor: AppColors.bg,
      body: SafeArea(
        child: Column(
          children: [
            // Minimal header — no back button (payment in progress)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
              child: Row(
                children: [
                  Container(
                    width: 48, height: 48,
                    decoration: BoxDecoration(
                      color: _gatewayColor.withOpacity(0.1),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(_gatewayIcon, color: _gatewayColor, size: 24),
                  ),
                  const SizedBox(width: 12),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(activeTenant.value.appName,
                          style: TextStyle(
                              fontSize: 15, fontWeight: FontWeight.w700,
                              color: AppColors.textMain, height: 1.1, letterSpacing: -0.3)),
                      Text(widget.gatewayName,
                          style: TextStyle(
                              fontSize: 15, fontWeight: FontWeight.w700,
                              color: AppColors.textMain, height: 1.1, letterSpacing: -0.3)),
                    ],
                  ),
                ],
              ),
            ),
            Divider(height: 32, color: AppColors.border),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: _buildBody(),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    switch (_step) {
      case _GatewayStep.creating:
        return _buildLoadingState('Initializing payment...', 'Connecting to ${widget.gatewayName}');
      case _GatewayStep.redirecting:
      case _GatewayStep.waitingUser:
        return _buildWaitingState();
      case _GatewayStep.processing:
        return _buildLoadingState('Processing payment...', 'Recording your transaction');
      case _GatewayStep.success:
        return _buildLoadingState('Payment confirmed!', 'Generating your receipt...');
      case _GatewayStep.failed:
        return _buildFailedState();
    }
  }

  Widget _buildLoadingState(String title, String subtitle) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        ScaleTransition(
          scale: _pulseAnim,
          child: Container(
            width: 96, height: 96,
            decoration: BoxDecoration(
              color: _gatewayColor.withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(_gatewayIcon, size: 48, color: _gatewayColor),
          ),
        ),
        const SizedBox(height: 36),
        CircularProgressIndicator(color: _gatewayColor, strokeWidth: 3),
        const SizedBox(height: 28),
        Text(title,
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textMain)),
        const SizedBox(height: 8),
        Text(subtitle,
            style: TextStyle(fontSize: 14, color: AppColors.textMuted)),
        const SizedBox(height: 32),
        // Amount pill
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: _gatewayColor.withOpacity(0.2)),
            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 12, offset: const Offset(0, 4))],
          ),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(_gatewayIcon, color: _gatewayColor, size: 20),
            const SizedBox(width: 10),
            Text('${widget.gatewayName}  ·  ₱${widget.amount.toStringAsFixed(2)}',
                style: TextStyle(fontWeight: FontWeight.w700, color: _gatewayColor, fontSize: 15)),
          ]),
        ),
      ],
    );
  }

  Widget _buildWaitingState() {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Container(
          width: 96, height: 96,
          decoration: BoxDecoration(
            color: _gatewayColor.withOpacity(0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(_gatewayIcon, size: 48, color: _gatewayColor),
        ),
        const SizedBox(height: 28),
        Text('Complete Payment in ${widget.gatewayName}',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textMain)),
        const SizedBox(height: 12),
        Text(
          'A ${widget.gatewayName} page has been opened.\n'
          'Complete your payment of ₱${widget.amount.toStringAsFixed(2)},\nthen return here.',
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 14, color: AppColors.textMuted, height: 1.6),
        ),
        const SizedBox(height: 32),
        if (_checkoutUrl != null) ...[
          SizedBox(
            width: double.infinity, height: 52,
            child: ElevatedButton.icon(
              onPressed: () => _launchExternalUrl(_checkoutUrl!),
              icon: Icon(_gatewayIcon),
              label: Text('Open ${widget.gatewayName}',
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
              style: ElevatedButton.styleFrom(
                backgroundColor: _gatewayColor,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
          const SizedBox(height: 12),
        ],
        Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          SizedBox(
            width: 16, height: 16,
            child: CircularProgressIndicator(strokeWidth: 2, color: _gatewayColor),
          ),
          const SizedBox(width: 10),
          Text('Waiting for confirmation...',
              style: TextStyle(fontSize: 13, color: AppColors.textMuted)),
        ]),
        const SizedBox(height: 32),
        TextButton(
          onPressed: () { _pollTimer?.cancel(); Navigator.pop(context, false); },
          child: Text('Cancel Payment',
              style: TextStyle(color: AppColors.secondary, fontWeight: FontWeight.w600)),
        ),
      ],
    );
  }

  Widget _buildFailedState() {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Container(
          width: 96, height: 96,
          decoration: BoxDecoration(
            color: AppColors.secondary.withOpacity(0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(Icons.error_outline_rounded, size: 48, color: AppColors.secondary),
        ),
        const SizedBox(height: 28),
        Text('Payment Failed',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: AppColors.secondary)),
        const SizedBox(height: 12),
        Text(_errorMessage ?? 'Something went wrong.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 14, color: AppColors.textMuted, height: 1.5)),
        const SizedBox(height: 36),
        SizedBox(
          width: double.infinity, height: 52,
          child: ElevatedButton.icon(
            onPressed: () {
              if (_usesPaymongo) { _initPaymongo(); } else { _processDirectPayment(); }
            },
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Try Again', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
            style: ElevatedButton.styleFrom(
              backgroundColor: _gatewayColor,
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
          ),
        ),
        const SizedBox(height: 12),
        TextButton(
          onPressed: () => Navigator.pop(context, false),
          child: Text('Go Back', style: TextStyle(color: AppColors.textMuted, fontWeight: FontWeight.w600)),
        ),
      ],
    );
  }
}

enum _GatewayStep { creating, redirecting, waitingUser, processing, success, failed }
