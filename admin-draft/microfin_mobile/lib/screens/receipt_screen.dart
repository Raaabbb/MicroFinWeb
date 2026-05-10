import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme.dart';
import '../main.dart';

class ReceiptScreen extends StatelessWidget {
  final String paymentReference;
  final double amount;
  final double principalPaid;
  final double interestPaid;
  final String paymentMethod;
  final String loanNumber;
  final String borrowerName;
  final String paymentDate;
  final String? remainingBalance;
  final String status;

  ReceiptScreen({
    super.key,
    required this.paymentReference,
    required this.amount,
    required this.principalPaid,
    required this.interestPaid,
    required this.paymentMethod,
    required this.loanNumber,
    required this.borrowerName,
    required this.paymentDate,
    this.remainingBalance,
    this.status = 'Posted',
  });

  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.themePrimaryColor;
    final appName = activeTenant.value.appName;

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
          child: Column(
            children: [
              // ── Custom Header ──────────────────────────────────────
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  GestureDetector(
                    onTap: () => Navigator.pop(context),
                    child: Container(
                      width: 48,
                      height: 48,
                      decoration: const BoxDecoration(
                          shape: BoxShape.circle, color: Color(0xFF1F2937)),
                      child: const Icon(Icons.close_rounded,
                          color: Colors.white, size: 24),
                    ),
                  ),
                  const Text('Payment Receipt',
                      style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF111827),
                          letterSpacing: -0.5)),
                  IconButton(
                    onPressed: () => _shareReceipt(context),
                    icon: const Icon(Icons.share_outlined,
                        color: Color(0xFF111827), size: 24),
                  ),
                ],
              ),
              const SizedBox(height: 28),

              // ── Success banner ──────────────────────────────────────
              Container(
                width: double.infinity,
                padding:
                    const EdgeInsets.symmetric(vertical: 40, horizontal: 24),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [primary, primary.withOpacity(0.85)],
                  ),
                  borderRadius: BorderRadius.circular(32),
                  boxShadow: [
                    BoxShadow(
                      color: primary.withOpacity(0.3),
                      blurRadius: 20,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    Container(
                      width: 72,
                      height: 72,
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.15),
                        shape: BoxShape.circle,
                        border: Border.all(
                            color: Colors.white.withOpacity(0.2), width: 2),
                      ),
                      child: const Icon(Icons.check_circle_rounded,
                          color: Colors.white, size: 44),
                    ),
                    const SizedBox(height: 20),
                    const Text('Payment Successful!',
                        style: TextStyle(
                            color: Colors.white,
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.5)),
                    const SizedBox(height: 8),
                    Text(
                      '₱${amount.toStringAsFixed(2)}',
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 44,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -1.5,
                          height: 1.1),
                    ),
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 16, vertical: 8),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.2),
                        borderRadius: BorderRadius.circular(100),
                      ),
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        Icon(_methodIcon(paymentMethod),
                            color: Colors.white, size: 16),
                        const SizedBox(width: 8),
                        Text(paymentMethod,
                            style: const TextStyle(
                                color: Colors.white,
                                fontSize: 13,
                                fontWeight: FontWeight.w800)),
                      ]),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // ── Receipt card ────────────────────────────────────────
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(28),
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.04),
                      blurRadius: 16,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Header
                    Padding(
                      padding: const EdgeInsets.fromLTRB(24, 24, 24, 0),
                      child: Row(
                        children: [
                          Icon(Icons.receipt_long_rounded,
                              color: primary, size: 20),
                          const SizedBox(width: 10),
                          const Text('Official Receipt',
                              style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w900,
                                  color: Color(0xFF111827),
                                  letterSpacing: -0.5)),
                          const Spacer(),
                          _statusBadge(status, primary),
                        ],
                      ),
                    ),
                    const SizedBox(height: 6),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: Text(appName,
                          style: const TextStyle(
                              fontSize: 12,
                              color: Color(0xFF6B7280),
                              fontWeight: FontWeight.w500)),
                    ),

                    const SizedBox(height: 24),
                    _dottedDivider(),
                    const SizedBox(height: 20),

                    // Details
                    _receiptRow('Reference No.', paymentReference, primary,
                        isBold: true),
                    _divider(),
                    _receiptRow('Borrower', borrowerName, primary),
                    _divider(),
                    _receiptRow('Loan No.', loanNumber, primary),
                    _divider(),
                    _receiptRow('Payment Date', paymentDate, primary),
                    _divider(),
                    _receiptRow('Payment Method', paymentMethod, primary),

                    const SizedBox(height: 20),
                    _dottedDivider(),
                    const SizedBox(height: 24),

                    // Payment breakdown
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 24),
                      child: Text('PAYMENT BREAKDOWN',
                          style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFF6B7280),
                              letterSpacing: 1.0)),
                    ),
                    const SizedBox(height: 14),
                    _receiptRow('Principal Amount',
                        '₱${principalPaid.toStringAsFixed(2)}', primary),
                    _divider(),
                    _receiptRow('Interest amount',
                        '₱${interestPaid.toStringAsFixed(2)}', primary),
                    if (remainingBalance != null) ...[
                      _divider(),
                      _receiptRow('Remaining Balance', '₱$remainingBalance',
                          const Color(0xFFEF4444)),
                    ],

                    const SizedBox(height: 24),
                    _dottedDivider(),
                    const SizedBox(height: 20),

                    // Total
                    Container(
                      margin: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: primary.withOpacity(0.06),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: primary.withOpacity(0.12)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Total Paid',
                              style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w800,
                                  color: Color(0xFF111827))),
                          Text('₱${amount.toStringAsFixed(2)}',
                              style: TextStyle(
                                  fontSize: 22,
                                  fontWeight: FontWeight.w900,
                                  color: primary,
                                  letterSpacing: -0.5)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // ── Actions ───────────────────────────────
              Row(
                children: [
                  Expanded(
                    child: SizedBox(
                      height: 52,
                      child: OutlinedButton.icon(
                        onPressed: () {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                                content: Text('Receipt downloaded to device!')),
                          );
                        },
                        icon: Icon(Icons.download_rounded,
                            color: primary, size: 16),
                        label: Text('Download',
                            style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: primary)),
                        style: OutlinedButton.styleFrom(
                            side: BorderSide(color: primary, width: 1.5),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14))),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: SizedBox(
                      height: 52,
                      child: OutlinedButton.icon(
                        onPressed: () {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Sending to printer...')),
                          );
                        },
                        icon:
                            Icon(Icons.print_rounded, color: primary, size: 16),
                        label: Text('Print',
                            style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: primary)),
                        style: OutlinedButton.styleFrom(
                            side: BorderSide(color: primary, width: 1.5),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14))),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: OutlinedButton.icon(
                  onPressed: () {
                    Clipboard.setData(ClipboardData(text: paymentReference));
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Row(children: [
                          const Icon(Icons.copy_rounded,
                              color: Colors.white, size: 18),
                          const SizedBox(width: 8),
                          Text('Reference $paymentReference copied!'),
                        ]),
                        backgroundColor: primary,
                        behavior: SnackBarBehavior.floating,
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12)),
                      ),
                    );
                  },
                  icon: Icon(Icons.copy_rounded, color: primary, size: 18),
                  label: Text('Copy Reference Number',
                      style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: primary)),
                  style: OutlinedButton.styleFrom(
                    side: BorderSide(color: primary, width: 1.5),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14)),
                  ),
                ),
              ),
              const SizedBox(height: 24),

              // ── Done button ──────────────────────────────────────────
              SizedBox(
                width: double.infinity,
                height: 56,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.of(context).popUntil((route) =>
                        route.isFirst || route.settings.name == '/home');
                    Navigator.of(context).pop(true);
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: primary,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    shadowColor: primary.withOpacity(0.4),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(18)),
                  ),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.home_rounded, size: 20),
                      SizedBox(width: 10),
                      Text('Back to Home',
                          style: TextStyle(
                              fontSize: 16, fontWeight: FontWeight.w800)),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 24),

              // ── Footer note ─────────────────────────────────────────
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: primary.withOpacity(0.05),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: primary.withOpacity(0.1)),
                ),
                child:
                    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Icon(Icons.info_outline_rounded, color: primary, size: 18),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'This receipt has been automatically recorded. Please keep the reference number for your records.',
                      style: TextStyle(
                          fontSize: 12,
                          color: primary.withOpacity(0.8),
                          height: 1.5,
                          fontWeight: FontWeight.w500),
                    ),
                  ),
                ]),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ── Helpers ──────────────────────────────────────────────────────────────

  Widget _receiptRow(String label, String value, Color primary,
      {bool isBold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(label,
            style: const TextStyle(
                fontSize: 14,
                color: Color(0xFF6B7280),
                fontWeight: FontWeight.w600)),
        Flexible(
          child: Text(value,
              textAlign: TextAlign.right,
              style: TextStyle(
                  fontSize: isBold ? 15 : 14,
                  fontWeight: isBold ? FontWeight.w900 : FontWeight.w700,
                  color: isBold ? primary : const Color(0xFF111827))),
        ),
      ]),
    );
  }

  Widget _divider() => const Divider(
      height: 1, indent: 24, endIndent: 24, color: Color(0xFFF3F4F6));

  Widget _dottedDivider() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24),
      child: Row(
        children: List.generate(
          36,
          (i) => Expanded(
            child: Container(
              height: 1.5,
              margin: const EdgeInsets.symmetric(horizontal: 1.5),
              color: i % 2 == 0 ? const Color(0xFFE5E7EB) : Colors.transparent,
            ),
          ),
        ),
      ),
    );
  }

  Widget _statusBadge(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: const Color(0xFF10B981).withOpacity(0.1),
        borderRadius: BorderRadius.circular(10),
      ),
      child: const Text(
        'POSTED',
        style: TextStyle(
            fontSize: 10,
            fontWeight: FontWeight.w900,
            color: Color(0xFF10B981),
            letterSpacing: 0.5),
      ),
    );
  }

  IconData _methodIcon(String method) {
    switch (method.toLowerCase()) {
      case 'gcash':
        return Icons.account_balance_wallet_rounded;
      case 'paymaya':
      case 'maya':
        return Icons.credit_card_rounded;
      case 'bank transfer':
        return Icons.account_balance_rounded;
      default:
        return Icons.payment_rounded;
    }
  }

  void _shareReceipt(BuildContext context) {
    final text = '''
🧾 PAYMENT RECEIPT
==================
Reference: $paymentReference
Borrower:  $borrowerName
Loan No.:  $loanNumber
Amount:    ₱${amount.toStringAsFixed(2)}
Method:    $paymentMethod
Date:      $paymentDate
Status:    $status

Principal: ₱${principalPaid.toStringAsFixed(2)}
Interest:  ₱${interestPaid.toStringAsFixed(2)}
${remainingBalance != null ? 'Balance:  ₱$remainingBalance' : ''}
==================
Thank you for your payment!
''';
    Clipboard.setData(ClipboardData(text: text));
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Receipt copied to clipboard!')),
    );
  }
}
