import 'dart:convert';
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';

class LoanApplicationScreen extends StatefulWidget {
  const LoanApplicationScreen({super.key});
  @override
  State<LoanApplicationScreen> createState() => _LoanApplicationScreenState();
}

class _LoanApplicationScreenState extends State<LoanApplicationScreen> with SingleTickerProviderStateMixin {
  final PageController _pageCtrl = PageController();
  int _currentStep = 0;
  final int _totalSteps = 3;

  // ──── STEP 0: Loan Setup ────
  List<dynamic> _products = [];
  bool _isLoadingProducts = true;
  int _selectedProduct = 0;
  double _loanAmount = 5000;
  int _selectedTerm = 6;

  List<int> get _terms {
    if (_products.isEmpty) return [];
    int minT = (_product['min_term'] ?? 6) as int;
    int maxT = (_product['max_term'] ?? 24) as int;
    return [6, 9, 12, 18, 24, 36, 48, 60].where((t) => t >= minT && t <= maxT).toList();
  }

  Map<String, dynamic> get _product =>
      _products.isNotEmpty && _selectedProduct < _products.length ? _products[_selectedProduct] : {};
  double get _rate => _product.isNotEmpty ? (_product['rate'] as num).toDouble() / 100 : 0.0;
  double get _monthly => _product.isNotEmpty ? (_loanAmount + (_loanAmount * _rate * _selectedTerm)) / _selectedTerm : 0.0;

  // ──── STEP 1: Purpose & Docs ────
  String _purposeCategory = 'Personal';
  final _purposeDescCtrl = TextEditingController();
  List<dynamic> _docTypes = [];
  final Map<int, String?> _selectedDocs = {};
  final Map<String, TextEditingController> _appDataCtrls = {};

  bool _isSubmitting = false;

  late AnimationController _pulseController;
  late Animation<double> _pulseAnimation;

  @override
  void initState() {
    super.initState();
    _fetchProducts();
    _fetchDocTypes();
    
    _pulseController = AnimationController(vsync: this, duration: const Duration(seconds: 2))..repeat(reverse: true);
    _pulseAnimation = Tween<double>(begin: 1.0, end: 1.05).animate(CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut));
  }

  Future<void> _fetchProducts() async {
    try {
      final resp = await http.get(Uri.parse(
          ApiConfig.getUrl('api_get_products.php?tenant_id=${activeTenant.value.id}')));
      final data = jsonDecode(resp.body);
      if (data['success'] == true) {
        setState(() {
          _products = data['products'];
          if (_products.isNotEmpty) {
            _selectedProduct = 0;
            _loanAmount = (_products[0]['min'] as num).toDouble();
            final t = _terms;
            if (t.isNotEmpty) _selectedTerm = t.contains(12) ? 12 : t.first;
          }
          _isLoadingProducts = false;
        });
      }
    } catch (e) {
      setState(() => _isLoadingProducts = false);
    }
  }

  Future<void> _fetchDocTypes() async {
    try {
      final resp = await http.get(Uri.parse(ApiConfig.getUrl('api_get_doc_types.php')));
      final data = jsonDecode(resp.body);
      if (data['success'] == true) {
        setState(() { _docTypes = data['document_types']; });
      }
    } catch (e) {}
  }

  @override
  void dispose() {
    _pageCtrl.dispose();
    _purposeDescCtrl.dispose();
    for (var c in _appDataCtrls.values) { c.dispose(); }
    _pulseController.dispose();
    super.dispose();
  }

  TextEditingController _getDynamicCtrl(String key) {
    if (!_appDataCtrls.containsKey(key)) _appDataCtrls[key] = TextEditingController();
    return _appDataCtrls[key]!;
  }

  void _goNext() {
    if (_currentStep < _totalSteps - 1) {
      if (_currentStep == 0 && _products.isEmpty) {
        _showSnack('No products available. Please wait.');
        return;
      }
      if (_currentStep == 1) {
        if (_purposeDescCtrl.text.isEmpty) {
          _showSnack('Please describe the precise purpose of the loan.');
          return;
        }
        final reqDocs = _getRequiredDocs();
        for (var d in reqDocs) {
          int id = int.tryParse(d['document_type_id'].toString()) ?? 0;
          if (_selectedDocs[id] == null) {
            _showSnack('Complete uploading all required documents.');
            return;
          }
        }
      }
      HapticFeedback.lightImpact();
      setState(() => _currentStep++);
      _pageCtrl.animateToPage(_currentStep, duration: const Duration(milliseconds: 600), curve: Curves.easeOutExpo);
    }
  }

  void _goBack() {
    if (_currentStep > 0) {
      HapticFeedback.lightImpact();
      setState(() => _currentStep--);
      _pageCtrl.animateToPage(_currentStep, duration: const Duration(milliseconds: 500), curve: Curves.easeOutExpo);
    } else {
      Navigator.pop(context);
    }
  }

  List<dynamic> _getRequiredDocs() {
    if (_purposeCategory == 'Business') return _docTypes.where((d) => d['document_name'].toString().contains('Business Permit')).toList();
    if (_purposeCategory == 'Medical') return _docTypes.where((d) => d['document_name'].toString().contains('Medical Certificate')).toList();
    if (_purposeCategory == 'Education') return _docTypes.where((d) => d['document_name'].toString().contains('Enrollment')).toList();
    if (_purposeCategory == 'Housing') return _docTypes.where((d) => d['document_name'].toString().contains('Bill of Materials')).toList();
    if (_purposeCategory == 'Agricultural') return _docTypes.where((d) => d['document_name'].toString().contains('Land Title')).toList();
    return [];
  }

  List<dynamic> _getOptionalDocs() {
     if (_purposeCategory == 'Business') return _docTypes.where((d) => d['document_name'].toString().contains('DTI/SEC')).toList();
    if (_purposeCategory == 'Medical') return _docTypes.where((d) => d['document_name'].toString().contains('Clinical Abstract')).toList();
    if (_purposeCategory == 'Education') return _docTypes.where((d) => d['document_name'].toString().contains('School ID')).toList();
    return [];
  }

  Future<void> _submit() async {
    if (currentUser.value == null) return;
    HapticFeedback.mediumImpact();
    setState(() => _isSubmitting = true);
    try {
      final docs = <Map<String, dynamic>>[];
      _selectedDocs.forEach((id, path) {
        if (path != null) docs.add({'document_type_id': id, 'file_name': path.split('/').last, 'file_path': path});
      });
      Map<String, String> appData = {};
      _appDataCtrls.forEach((k, v) => appData[k] = v.text);
      final body = jsonEncode({
        'user_id': currentUser.value!['user_id'],
        'tenant_id': activeTenant.value.id,
        'product_id': _product['id'],
        'amount': _loanAmount,
        'term': _selectedTerm,
        'purpose_category': _purposeCategory,
        'purpose': _purposeDescCtrl.text,
        'app_data': jsonEncode(appData),
        'documents': docs,
      });
      final resp = await http.post(
        Uri.parse(ApiConfig.getUrl('api_apply_loan.php')),
        headers: {'Content-Type': 'application/json'},
        body: body,
      );
      final data = jsonDecode(resp.body);
      if (data['success'] == true) {
        if (!mounted) return;
        _showSuccessDialog(data['application_number']);
      } else {
        if (!mounted) return;
        _showSnack(data['message'] ?? 'Submission failed');
      }
    } catch (e) {
      _showSnack('Error: $e');
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _showSnack(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), behavior: SnackBarBehavior.floating, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), backgroundColor: const Color(0xFF1E293B)));
  }

  void _showSuccessDialog(String appNum) {
    final primary = activeTenant.value.themePrimaryColor;
    showGeneralDialog(
      context: context,
      barrierDismissible: false,
      barrierColor: Colors.black.withOpacity(0.4),
      pageBuilder: (context, anim1, anim2) {
        return Align(
          alignment: Alignment.center,
          child: ScaleTransition(
            scale: CurvedAnimation(parent: anim1, curve: Curves.easeOutBack),
            child: Material(
              color: Colors.transparent,
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 24),
                padding: const EdgeInsets.all(32),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(32),
                  boxShadow: [BoxShadow(color: primary.withOpacity(0.2), blurRadius: 40, spreadRadius: -10, offset: const Offset(0, 20))],
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    ScaleTransition(
                      scale: _pulseAnimation,
                      child: Container(
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(colors: [primary.withOpacity(0.1), primary.withOpacity(0.2)]),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(Icons.verified_rounded, size: 56, color: primary),
                      ),
                    ),
                    const SizedBox(height: 32),
                    Text('Application Succesful!', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: AppColors.textMain, letterSpacing: -0.5)),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(20)),
                      child: Text('Reference ID: $appNum', style: TextStyle(fontWeight: FontWeight.w800, color: primary, fontSize: 13, letterSpacing: 0.5)),
                    ),
                    const SizedBox(height: 16),
                    Text('Your loan application was submitted successfully and will be processed using your verified KYC.',
                        textAlign: TextAlign.center, style: TextStyle(fontSize: 14, color: AppColors.textMuted, height: 1.6)),
                    const SizedBox(height: 32),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: primary,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        minimumSize: const Size(double.infinity, 56),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                      ),
                      onPressed: () {
                        Navigator.pop(context); // dialog
                        Navigator.pop(context); // screen
                      },
                      child: const Text('Return to Home', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                    )
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  // ─── STUNNING BUILD ──────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.themePrimaryColor;
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Padding(
          padding: const EdgeInsets.only(left: 12),
          child: IconButton(
            icon: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
                boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10)],
              ),
              child: const Icon(Icons.arrow_back_rounded, color: Color(0xFF0F172A), size: 20),
            ),
            onPressed: _goBack,
          ),
        ),
        title: Text(
          'Apply for Loan',
          style: TextStyle(color: const Color(0xFF0F172A), fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: -0.5),
        ),
        centerTitle: true,
        actions: [
           Padding(
             padding: const EdgeInsets.only(right: 20),
             child: Center(
               child: Text('Step ${_currentStep + 1} of 3', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: primary)),
             ),
           )
        ],
      ),
      body: Stack(
        children: [
          // Background soft gradient
          Positioned(
            top: -100,
            right: -100,
            child: Container(
              width: 300, height: 300,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: primary.withOpacity(0.08),
              ),
              child: BackdropFilter(
                filter: ImageFilter.blur(sigmaX: 50, sigmaY: 50),
                child: Container(color: Colors.transparent),
              ),
            ),
          ),
          SafeArea(
            bottom: false,
            child: Column(
              children: [
                _buildGorgeousStepper(primary),
                Expanded(
                  child: PageView(
                    controller: _pageCtrl,
                    physics: const NeverScrollableScrollPhysics(),
                    children: [
                      _buildStep0(primary),
                      _buildStep1(primary),
                      _buildStep2(primary),
                    ],
                  ),
                ),
                _buildStunningBottomNav(primary),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ─── GORGEOUS COMPONENTS ──────────────────────────────────────────────

  Widget _buildGorgeousStepper(Color primary) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: List.generate(_totalSteps, (index) {
          bool isActive = index == _currentStep;
          bool isPassed = index < _currentStep;
          return Expanded(
            child: Row(
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  width: isActive ? 32 : 24,
                  height: isActive ? 32 : 24,
                  decoration: BoxDecoration(
                    color: isActive || isPassed ? primary : Colors.white,
                    shape: BoxShape.circle,
                    border: Border.all(color: isActive || isPassed ? primary : const Color(0xFFE2E8F0), width: 2),
                    boxShadow: isActive ? [BoxShadow(color: primary.withOpacity(0.3), blurRadius: 12, spreadRadius: 2)] : [],
                  ),
                  child: Center(
                    child: isPassed
                      ? const Icon(Icons.check_rounded, color: Colors.white, size: 14)
                      : Text('${index + 1}', style: TextStyle(color: isActive ? Colors.white : const Color(0xFF94A3B8), fontSize: isActive ? 14 : 12, fontWeight: FontWeight.bold)),
                  ),
                ),
                if (index < _totalSteps - 1)
                  Expanded(
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 300),
                      height: 3,
                      margin: const EdgeInsets.symmetric(horizontal: 8),
                      decoration: BoxDecoration(
                        color: isPassed ? primary : const Color(0xFFE2E8F0),
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                  ),
              ],
            ),
          );
        }),
      ),
    );
  }

  Widget _buildStunningBottomNav(Color primary) {
    bool isLast = _currentStep == _totalSteps - 1;
    return Container(
      padding: EdgeInsets.fromLTRB(24, 16, 24, MediaQuery.of(context).padding.bottom + 20),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 20, offset: const Offset(0, -10))],
        borderRadius: const BorderRadius.only(topLeft: Radius.circular(32), topRight: Radius.circular(32)),
      ),
      child: SafeArea(
        top: false,
        child: ElevatedButton(
          onPressed: _isSubmitting ? null : () {
            if (isLast) { _submit(); } else { _goNext(); }
          },
          style: ElevatedButton.styleFrom(
            backgroundColor: primary,
            foregroundColor: Colors.white,
            elevation: 8,
            shadowColor: primary.withOpacity(0.4),
            minimumSize: const Size(double.infinity, 60),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          ),
          child: _isSubmitting
              ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 3))
              : Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(isLast ? 'Complete Application' : 'Continue', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, letterSpacing: 0.5)),
                    if (!isLast) ...[
                      const SizedBox(width: 8),
                      const Icon(Icons.arrow_forward_rounded, size: 20),
                    ]
                  ],
                ),
        ),
      ),
    );
  }

  Widget _buildStep0(Color primary) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Choose Product', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A), letterSpacing: -0.5)),
          const SizedBox(height: 16),
          if (_isLoadingProducts)
             Center(child: Padding(padding: const EdgeInsets.all(40), child: CircularProgressIndicator(color: primary)))
          else if (_products.isEmpty)
             const Center(child: Text('No loan products available at this time.', style: TextStyle(color: Color(0xFF64748B))))
          else
             ListView.separated(
               shrinkWrap: true,
               physics: const NeverScrollableScrollPhysics(),
               itemCount: _products.length,
               separatorBuilder: (_, __) => const SizedBox(height: 12),
               itemBuilder: (ctx, i) => _productCardPremium(i, _products[i], primary),
             ),
          
          if (!_isLoadingProducts && _products.isNotEmpty) ...[
             const SizedBox(height: 32),
             const Text('How much do you need?', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A), letterSpacing: -0.5)),
             const SizedBox(height: 24),
             Container(
               padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 20),
               decoration: BoxDecoration(
                 color: Colors.white,
                 borderRadius: BorderRadius.circular(24),
                 boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 20, offset: const Offset(0, 10))],
                 border: Border.all(color: const Color(0xFFF1F5F9)),
               ),
               child: Column(
                 children: [
                   Text('Requested Amount', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: primary.withOpacity(0.7), letterSpacing: 0.5)),
                   const SizedBox(height: 8),
                   Text('₱${_loanAmount.toStringAsFixed(2)}', style: const TextStyle(fontSize: 48, fontWeight: FontWeight.w900, color: Color(0xFF0F172A), letterSpacing: -1.5, height: 1.0)),
                   const SizedBox(height: 24),
                   SliderTheme(
                      data: SliderTheme.of(context).copyWith(
                        activeTrackColor: primary,
                        inactiveTrackColor: primary.withOpacity(0.1),
                        trackHeight: 8,
                        thumbColor: Colors.white,
                        thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 14, elevation: 6, pressedElevation: 10),
                        overlayColor: primary.withOpacity(0.2),
                      ),
                      child: Slider(
                        value: _loanAmount,
                        min: (_product['min'] as num).toDouble(),
                        max: (_product['max'] as num).toDouble(),
                        onChanged: (v) => setState(() { HapticFeedback.selectionClick(); _loanAmount = v; }),
                      ),
                   ),
                   Padding(
                     padding: const EdgeInsets.symmetric(horizontal: 16),
                     child: Row(
                       mainAxisAlignment: MainAxisAlignment.spaceBetween,
                       children: [
                         Text('Min ₱${(_product['min'] as num).toInt()}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8))),
                         Text('Max ₱${(_product['max'] as num).toInt()}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF94A3B8))),
                       ],
                     ),
                   )
                 ],
               ),
             ),
             const SizedBox(height: 32),
             const Text('Repayment Term', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A), letterSpacing: -0.5)),
             const SizedBox(height: 16),
             Wrap(
               spacing: 12,
               runSpacing: 12,
               children: _terms.map((t) {
                 final isSelected = _selectedTerm == t;
                 return GestureDetector(
                   onTap: () { HapticFeedback.mediumImpact(); setState(() => _selectedTerm = t); },
                   child: AnimatedContainer(
                     duration: const Duration(milliseconds: 200),
                     padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                     decoration: BoxDecoration(
                       gradient: isSelected ? LinearGradient(colors: [primary, primary.withOpacity(0.8)]) : null,
                       color: isSelected ? null : Colors.white,
                       borderRadius: BorderRadius.circular(16),
                       border: Border.all(color: isSelected ? primary : const Color(0xFFE2E8F0), width: isSelected ? 0 : 1.5),
                       boxShadow: isSelected ? [BoxShadow(color: primary.withOpacity(0.3), blurRadius: 12, offset: const Offset(0, 4))] : [],
                     ),
                     child: Text('$t mos', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: isSelected ? Colors.white : const Color(0xFF64748B))),
                   ),
                 );
               }).toList(),
             )
          ]
        ],
      ),
    );
  }

  Widget _productCardPremium(int index, Map<String, dynamic> p, Color primary) {
    bool sel = _selectedProduct == index;
    // Derive icon conditionally, similar to existing logic
    IconData icon = Icons.payments_rounded;
    String pName = (p['name'] ?? '').toString().toLowerCase();
    if (pName.contains('business')) icon = Icons.business_center_rounded;
    if (pName.contains('medical') || pName.contains('emergency')) icon = Icons.health_and_safety_rounded;
    if (pName.contains('education')) icon = Icons.school_rounded;

    return GestureDetector(
      onTap: () {
        HapticFeedback.lightImpact();
        setState(() {
          _selectedProduct = index;
          _loanAmount = ((p['min'] as num) + (p['max'] as num)) / 2;
          final t = _terms;
          if (t.isNotEmpty && !t.contains(_selectedTerm)) _selectedTerm = t.first;
        });
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOutCubic,
        padding: EdgeInsets.all(sel ? 20 : 16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: sel ? primary : const Color(0xFFF1F5F9), width: sel ? 2 : 1),
          boxShadow: sel 
            ? [BoxShadow(color: primary.withOpacity(0.15), blurRadius: 24, spreadRadius: -5, offset: const Offset(0, 10))] 
            : [BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 10)],
        ),
        child: Row(
          children: [
            Container(
              width: 56, height: 56,
              decoration: BoxDecoration(
                gradient: sel 
                  ? LinearGradient(colors: [primary.withOpacity(0.15), primary.withOpacity(0.05)], begin: Alignment.topLeft, end: Alignment.bottomRight)
                  : const LinearGradient(colors: [Color(0xFFF1F5F9), Color(0xFFF8FAFC)]),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Icon(icon, color: sel ? primary : const Color(0xFF94A3B8), size: 28),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(p['name'] ?? 'Product', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: sel ? primary : const Color(0xFF0F172A))),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(color: sel ? primary.withOpacity(0.1) : const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(8)),
                    child: Text('${p['rate']}% Int. Rate', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: sel ? primary : const Color(0xFF64748B))),
                  ),
                ],
              ),
            ),
            AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              width: 24, height: 24,
              decoration: BoxDecoration(
                color: sel ? primary : Colors.transparent,
                shape: BoxShape.circle,
                border: Border.all(color: sel ? primary : const Color(0xFFCBD5E1), width: 2),
              ),
              child: sel ? const Icon(Icons.check, color: Colors.white, size: 14) : null,
            )
          ],
        ),
      ),
    );
  }

  Widget _buildStep1(Color primary) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Purpose Details', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A), letterSpacing: -0.5)),
          const SizedBox(height: 16),
          _premiumFormCard([
            _premiumDropdown('Category', _purposeCategory, ['Personal', 'Business', 'Education', 'Medical', 'Housing', 'Agricultural'], (v) => setState((){ _purposeCategory = v!; _selectedDocs.clear();})),
             const SizedBox(height: 16),
            _premiumInput('Specific Purpose', _purposeDescCtrl, lines: 3),
          ]),
          const SizedBox(height: 24),
          if (_purposeCategory == 'Business') _premiumFormCard([
            Text('Business Data', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: primary)),
            const SizedBox(height: 12),
            _premiumInput('Business Name', _getDynamicCtrl('business_name')),
            const SizedBox(height: 12),
            _premiumInput('Years in Operation', _getDynamicCtrl('business_years'), keyboard: TextInputType.number),
          ]),
          if (_purposeCategory == 'Medical') _premiumFormCard([
            Text('Medical Data', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: primary)),
            const SizedBox(height: 12),
            _premiumInput('Patient Name', _getDynamicCtrl('med_patient_name')),
            const SizedBox(height: 12),
            _premiumInput('Hospital', _getDynamicCtrl('med_hospital')),
          ]),
          if (_purposeCategory == 'Education') _premiumFormCard([
            Text('Education Data', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: primary)),
            const SizedBox(height: 12),
            _premiumInput('Student Name', _getDynamicCtrl('edu_student_name')),
            const SizedBox(height: 12),
            _premiumInput('School', _getDynamicCtrl('edu_school')),
          ]),

          const SizedBox(height: 24),
          const Text('Required Documents', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A), letterSpacing: -0.5)),
          const SizedBox(height: 16),
          
          if (_getRequiredDocs().isEmpty && _getOptionalDocs().isEmpty)
              Container(padding: const EdgeInsets.all(20), decoration: BoxDecoration(color: const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(16)), child: Row(children: [const Icon(Icons.verified_user_rounded, color: Color(0xFF64748B)), const SizedBox(width: 12), const Expanded(child: Text('Only your KYC basic documents are required for this.', style: TextStyle(color: Color(0xFF64748B), fontWeight: FontWeight.w600)))]))
          else ...[
             ..._getRequiredDocs().map((d) => _premiumDocPicker(d, primary, true)),
             if (_getOptionalDocs().isNotEmpty) ...[
                 const SizedBox(height: 8),
                 Text('Optional Documents', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: const Color(0xFF94A3B8))),
                 const SizedBox(height: 12),
                 ..._getOptionalDocs().map((d) => _premiumDocPicker(d, primary, false)),
             ]
          ]
        ],
      ),
    );
  }

  Widget _premiumDocPicker(dynamic d, Color primary, bool required) {
    int id = int.tryParse(d['document_type_id'].toString()) ?? 0;
    bool isSel = _selectedDocs[id] != null;
    return GestureDetector(
      onTap: () => setState(() => _selectedDocs[id] = '/dummy/path_$id.jpg'),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isSel ? primary.withOpacity(0.04) : Colors.white,
          border: Border.all(color: isSel ? primary.withOpacity(0.5) : const Color(0xFFE2E8F0)),
          borderRadius: BorderRadius.circular(20),
          boxShadow: [if (isSel) BoxShadow(color: primary.withOpacity(0.1), blurRadius: 10, offset: const Offset(0, 4))],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: isSel ? primary : const Color(0xFFF1F5F9), borderRadius: BorderRadius.circular(12)),
              child: Icon(isSel ? Icons.cloud_done_rounded : Icons.cloud_upload_rounded, color: isSel ? Colors.white : const Color(0xFF94A3B8)),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${d['document_name']}${required ? ' *' : ''}', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: isSel ? primary : const Color(0xFF334155))),
                  const SizedBox(height: 4),
                  Text(isSel ? 'img_doc_uploaded.jpg' : 'Tap to select file', style: TextStyle(fontSize: 12, color: isSel ? primary.withOpacity(0.7) : const Color(0xFF94A3B8))),
                ],
              ),
            ),
            if (isSel) Icon(Icons.check_circle_rounded, color: primary)
          ],
        ),
      ),
    );
  }

  Widget _buildStep2(Color primary) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
           Container(
             padding: const EdgeInsets.all(24),
             decoration: BoxDecoration(
               gradient: LinearGradient(
                 colors: [primary, Color.lerp(primary, Colors.black, 0.3)!],
                 begin: Alignment.topLeft, end: Alignment.bottomRight,
               ),
               borderRadius: BorderRadius.circular(32),
               boxShadow: [BoxShadow(color: primary.withOpacity(0.4), blurRadius: 24, offset: const Offset(0, 10))],
             ),
             child: Column(
               children: [
                 Row(
                   mainAxisAlignment: MainAxisAlignment.spaceBetween,
                   children: [
                     Container(
                       padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                       decoration: BoxDecoration(color: Colors.white.withOpacity(0.2), borderRadius: BorderRadius.circular(20)),
                       child: Text(_product['name'] ?? '', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                     ),
                     Text('$_selectedTerm Months', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                   ],
                 ),
                 const SizedBox(height: 24),
                 const Text('Total Proceeds', style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w600)),
                 const SizedBox(height: 4),
                 Text('₱${_loanAmount.toStringAsFixed(2)}', style: const TextStyle(color: Colors.white, fontSize: 44, fontWeight: FontWeight.w900, letterSpacing: -1)),
                 const SizedBox(height: 24),
                 Container(height: 1, color: Colors.white.withOpacity(0.2)),
                 const SizedBox(height: 20),
                 Row(
                   mainAxisAlignment: MainAxisAlignment.spaceBetween,
                   children: [
                     _reviewMetric('Monthly Pay', '₱${_monthly.toStringAsFixed(2)}'),
                     _reviewMetric('Interest', '${_rate * 100 ~/ 1}%'),
                     _reviewMetric('Total Pay', '₱${(_monthly * _selectedTerm).toStringAsFixed(2)}'),
                   ],
                 )
               ],
             ),
           ),
           const SizedBox(height: 32),
           const Text('Application Summary', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF0F172A), letterSpacing: -0.5)),
           const SizedBox(height: 16),
           Container(
             padding: const EdgeInsets.all(20),
             decoration: BoxDecoration(
               color: Colors.white,
               borderRadius: BorderRadius.circular(24),
               border: Border.all(color: const Color(0xFFF1F5F9)),
               boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 10)],
             ),
             child: Column(
               children: [
                 _summaryRow('Category', _purposeCategory),
                 const Divider(color: Color(0xFFF1F5F9), height: 24),
                 _summaryRow('Description', _purposeDescCtrl.text),
                 const Divider(color: Color(0xFFF1F5F9), height: 24),
                 _summaryRow('Documents', '${_selectedDocs.values.where((v) => v != null).length} Files Attached'),
               ],
             ),
           ),
           const SizedBox(height: 20),
           Container(
             padding: const EdgeInsets.all(16),
             decoration: BoxDecoration(color: primary.withOpacity(0.05), borderRadius: BorderRadius.circular(16)),
             child: Row(
               children: [
                 Icon(Icons.shield_rounded, color: primary),
                 const SizedBox(width: 12),
                 const Expanded(child: Text('Your application is completely secure and will be evaluated subject to credit approval.', style: TextStyle(fontSize: 12, color: Color(0xFF64748B), height: 1.5))),
               ],
             ),
           )
        ],
      ),
    );
  }

  Widget _reviewMetric(String label, String value) {
    return Column(
      children: [
        Text(label, style: TextStyle(color: Colors.white.withOpacity(0.7), fontSize: 12, fontWeight: FontWeight.w600)),
        const SizedBox(height: 4),
        Text(value, style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
      ],
    );
  }

  Widget _summaryRow(String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(width: 100, child: Text(label, style: const TextStyle(color: Color(0xFF64748B), fontSize: 13, fontWeight: FontWeight.w600))),
        Expanded(child: Text(value, style: const TextStyle(color: Color(0xFF334155), fontSize: 14, fontWeight: FontWeight.bold))),
      ],
    );
  }

  // ─── GORGEOUS FORMS ──────────────────────────────────────────────────

  Widget _premiumFormCard(List<Widget> children) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 10)],
        border: Border.all(color: const Color(0xFFF1F5F9)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: children),
    );
  }

  Widget _premiumDropdown(String label, String value, List<String> items, Function(String?) onChanged) {
    return DropdownButtonFormField<String>(
      value: value,
      onChanged: onChanged,
      icon: const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF64748B)),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(color: Color(0xFF94A3B8), fontWeight: FontWeight.bold),
        filled: true,
        fillColor: const Color(0xFFF8FAFC),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      ),
      items: items.map((e) => DropdownMenuItem(value: e, child: Text(e, style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF334155))))).toList(),
    );
  }

  Widget _premiumInput(String label, TextEditingController ctrl, {int lines = 1, TextInputType? keyboard}) {
    return TextFormField(
      controller: ctrl,
      maxLines: lines,
      keyboardType: keyboard,
      style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF334155)),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(color: Color(0xFF94A3B8), fontWeight: FontWeight.bold),
        filled: true,
        fillColor: const Color(0xFFF8FAFC),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      ),
    );
  }
}