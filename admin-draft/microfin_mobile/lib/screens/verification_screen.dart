import 'dart:convert';
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

class _LoanApplicationScreenState extends State<LoanApplicationScreen> {
  final PageController _pageCtrl = PageController();
  int _currentStep = 0;
  final int _totalSteps = 6;

  // ──── STEP 1: Loan Setup ────
  List<dynamic> _products = [];
  bool _isLoadingProducts = true;
  int _selectedProduct = 0;
  double _loanAmount = 25000;
  int _selectedTerm = 12;
  final _purposeCtrl = TextEditingController();

  List<int> get _terms {
    if (_products.isEmpty) return [];
    int minT = (_product['min_term'] ?? 3) as int;
    int maxT = (_product['max_term'] ?? 24) as int;
    return [3, 6, 12, 18, 24, 36, 48, 60].where((t) => t >= minT && t <= maxT).toList();
  }

  Map<String, dynamic> get _product =>
      _products.isNotEmpty && _selectedProduct < _products.length ? _products[_selectedProduct] : {};
  double get _rate => _product.isNotEmpty ? (_product['rate'] as num).toDouble() / 100 : 0.0;
  double get _monthly => _product.isNotEmpty ? (_loanAmount + (_loanAmount * _rate * _selectedTerm)) / _selectedTerm : 0.0;
  double get _totalInterest => _product.isNotEmpty ? _monthly * _selectedTerm - _loanAmount : 0.0;

  // ──── STEP 2: Personal Info ────
  final _emailCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _dobCtrl = TextEditingController();
  String _gender = 'Male';
  String _civilStatus = 'Single';
  String _employmentStatus = 'Employed';
  final _occupationCtrl = TextEditingController();
  final _employerCtrl = TextEditingController();
  final _employerContactCtrl = TextEditingController();
  final _monthlyIncomeCtrl = TextEditingController();

  // ──── STEP 3: Address ────
  final _houseNoCtrl = TextEditingController();
  final _streetCtrl = TextEditingController();
  final _barangayCtrl = TextEditingController();
  final _cityCtrl = TextEditingController();
  final _provinceCtrl = TextEditingController();
  final _postalCtrl = TextEditingController();
  bool _sameAsPermanent = false;
  final _permHouseCtrl = TextEditingController();
  final _permStreetCtrl = TextEditingController();
  final _permBarangayCtrl = TextEditingController();
  final _permCityCtrl = TextEditingController();
  final _permProvinceCtrl = TextEditingController();
  final _permPostalCtrl = TextEditingController();

  // ──── STEP 4: Co-maker ────
  bool _hasComaker = false;
  final _comakerNameCtrl = TextEditingController();
  final _comakerRelCtrl = TextEditingController();
  final _comakerContactCtrl = TextEditingController();
  final _comakerIncomeCtrl = TextEditingController();
  final _comakerAddressCtrl = TextEditingController();

  // ──── STEP 5: Documents ────
  bool _isLoadingDocs = true;
  List<dynamic> _docTypes = [];
  final Map<int, String?> _selectedDocs = {};

  // ──── Submit ────
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _fetchProducts();
    _fetchDocTypes();
    _prefillFromUser();
  }

  void _prefillFromUser() {
    final u = currentUser.value;
    if (u == null) return;
    _emailCtrl.text = u['email'] ?? '';
    _phoneCtrl.text = u['phone_number'] ?? '';
    _dobCtrl.text = u['date_of_birth'] ?? '';
  }

  Future<void> _fetchProducts() async {
    try {
      final resp = await http.get(Uri.parse(
          ApiConfig.getUrl('api_get_products.php?tenant_id=${activeTenant.value.id}')));
      final data = jsonDecode(resp.body);
      if (data['success'] == true) {
        setState(() {
          _products = data['products'];
          for (var p in _products) {
            if (p['type'] == 'Business Loan') {
              p['icon'] = Icons.business_center_outlined;
            } else if (p['type'] == 'Emergency Loan') p['icon'] = Icons.local_hospital_outlined;
            else p['icon'] = Icons.person_outline_rounded;
          }
          if (_products.isNotEmpty) {
            _selectedProduct = 0;
            _loanAmount = ((_products[0]['min'] as num) + (_products[0]['max'] as num)) / 2;
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
      final resp = await http.get(Uri.parse(
          ApiConfig.getUrl('api_get_doc_types.php')));
      final data = jsonDecode(resp.body);
      if (data['success'] == true) {
        setState(() {
          _docTypes = (data['document_types'] as List)
              .where((doc) {
                final name = doc['document_name'].toString().toLowerCase();
                bool isKycDoc = name.contains('id') || 
                               name.contains('income') || 
                               name.contains('billing') || 
                               name.contains('legitimacy');
                bool isLoanSpecific = name.contains('school') || 
                                     name.contains('enrollment') || 
                                     name.contains('medical') || 
                                     name.contains('permit') || 
                                     name.contains('business');
                return doc['is_required'] == '1' && isKycDoc && !isLoanSpecific;
              })
              .toList();
          _isLoadingDocs = false;
        });
      }
    } catch (e) {
      setState(() => _isLoadingDocs = false);
    }
  }

  @override
  void dispose() {
    _pageCtrl.dispose();
    for (final c in [
      _purposeCtrl, _emailCtrl, _phoneCtrl, _dobCtrl, _occupationCtrl,
      _employerCtrl, _employerContactCtrl, _monthlyIncomeCtrl,
      _houseNoCtrl, _streetCtrl, _barangayCtrl, _cityCtrl, _provinceCtrl, _postalCtrl,
      _permHouseCtrl, _permStreetCtrl, _permBarangayCtrl, _permCityCtrl, _permProvinceCtrl, _permPostalCtrl,
      _comakerNameCtrl, _comakerRelCtrl, _comakerContactCtrl, _comakerIncomeCtrl, _comakerAddressCtrl,
    ]) { c.dispose(); }
    super.dispose();
  }

  void _goNext() {
    if (_currentStep < _totalSteps - 1) {
      HapticFeedback.lightImpact();
      setState(() => _currentStep++);
      _pageCtrl.animateToPage(_currentStep, duration: Duration(milliseconds: 300), curve: Curves.easeOut);
    }
  }

  void _goBack() {
    if (_currentStep > 0) {
      setState(() => _currentStep--);
      _pageCtrl.animateToPage(_currentStep, duration: Duration(milliseconds: 300), curve: Curves.easeOut);
    } else {
      Navigator.pop(context);
    }
  }

  bool _validateStep() {
    switch (_currentStep) {
      case 0: return _products.isNotEmpty && _loanAmount > 0 && _selectedTerm > 0;
      case 1: return _emailCtrl.text.isNotEmpty && _phoneCtrl.text.isNotEmpty;
      case 2: return _cityCtrl.text.isNotEmpty && _provinceCtrl.text.isNotEmpty;
      case 3: return !_hasComaker || (_comakerNameCtrl.text.isNotEmpty && _comakerContactCtrl.text.isNotEmpty);
      case 4:
        final required = _docTypes.where((d) => d['is_required'] == '1').toList();
        return required.every((d) => _selectedDocs[int.tryParse(d['document_type_id'].toString())] != null);
      default: return true;
    }
  }

  Future<void> _submit() async {
    if (currentUser.value == null) {
      _showSnack('Please log in first.');
      return;
    }
    HapticFeedback.mediumImpact();
    setState(() => _isSubmitting = true);

    try {
      final docs = <Map<String, dynamic>>[];
      _selectedDocs.forEach((id, path) {
        if (path != null) docs.add({'document_type_id': id, 'file_name': path.split('/').last, 'file_path': path});
      });

      final body = jsonEncode({
        'user_id': currentUser.value!['user_id'],
        'tenant_id': activeTenant.value.id,
        'product_id': _product['id'],
        'amount': _loanAmount,
        'term': _selectedTerm,
        'purpose': _purposeCtrl.text,
        'documents': docs,
        // Personal info
        'email': _emailCtrl.text,
        'phone_number': _phoneCtrl.text,
        'date_of_birth': _dobCtrl.text,
        'gender': _gender,
        'civil_status': _civilStatus,
        'employment_status': _employmentStatus,
        'occupation': _occupationCtrl.text,
        'employer_name': _employerCtrl.text,
        'employer_contact': _employerContactCtrl.text,
        'monthly_income': double.tryParse(_monthlyIncomeCtrl.text.replaceAll(',', '')) ?? 0,
        // Address
        'present_house_no': _houseNoCtrl.text,
        'present_street': _streetCtrl.text,
        'present_barangay': _barangayCtrl.text,
        'present_city': _cityCtrl.text,
        'present_province': _provinceCtrl.text,
        'present_postal_code': _postalCtrl.text,
        'same_as_present': _sameAsPermanent,
        'permanent_house_no': _sameAsPermanent ? _houseNoCtrl.text : _permHouseCtrl.text,
        'permanent_street': _sameAsPermanent ? _streetCtrl.text : _permStreetCtrl.text,
        'permanent_barangay': _sameAsPermanent ? _barangayCtrl.text : _permBarangayCtrl.text,
        'permanent_city': _sameAsPermanent ? _cityCtrl.text : _permCityCtrl.text,
        'permanent_province': _sameAsPermanent ? _provinceCtrl.text : _permProvinceCtrl.text,
        'permanent_postal_code': _sameAsPermanent ? _postalCtrl.text : _permPostalCtrl.text,
        // Comaker
        'has_comaker': _hasComaker,
        'comaker_name': _comakerNameCtrl.text,
        'comaker_relationship': _comakerRelCtrl.text,
        'comaker_contact': _comakerContactCtrl.text,
        'comaker_income': double.tryParse(_comakerIncomeCtrl.text) ?? 0,
        'comaker_address': _comakerAddressCtrl.text,
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
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), behavior: SnackBarBehavior.floating));
  }

  void _showSuccessDialog(String appNum) {
    final primary = activeTenant.value.themePrimaryColor;
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        contentPadding: EdgeInsets.all(28),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 72, height: 72, decoration: BoxDecoration(color: AppColors.bg, shape: BoxShape.circle),
              child: Icon(Icons.check_rounded, color: AppColors.primary, size: 36)),
          SizedBox(height: 20),
          Text('Application Submitted!', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textMain, letterSpacing: -0.4)),
          SizedBox(height: 10),
          Text('Ref No: $appNum', style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.textMain)),
          SizedBox(height: 10),
          Text('Your application is under review. We will notify you of any updates.',  style: TextStyle(fontSize: 14, color: AppColors.textMuted, height: 1.5)),
          SizedBox(height: 24),
          SizedBox(width: double.infinity, child: ElevatedButton(
            onPressed: () { Navigator.pop(context); Navigator.pop(context); },
            style: ElevatedButton.styleFrom(backgroundColor: primary, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)), padding: EdgeInsets.symmetric(vertical: 14)),
            child: Text('Done'),
          )),
        ]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.themePrimaryColor;
    final steps = ['Loan Setup', 'Personal Info', 'Address', 'Co-maker', 'Documents', 'Review'];

    return Scaffold(
      backgroundColor: AppColors.bg,
      body: Column(children: [
        // ── Header ──────────────────────────────────────────────
        _buildHeader(primary, steps),

        // ── Step Pages ──────────────────────────────────────────
        Expanded(
          child: PageView(
            controller: _pageCtrl,
            physics: NeverScrollableScrollPhysics(),
            children: [
              _buildStep1(primary),
              _buildStep2(primary),
              _buildStep3(primary),
              _buildStep4(primary),
              _buildStep5(primary),
              _buildStep6(primary),
            ],
          ),
        ),

        // ── Bottom Nav ───────────────────────────────────────────
        _buildBottomNav(primary, steps),
      ]),
    );
  }

  Widget _buildHeader(Color primary, List<String> steps) {
    return Container(
      padding: EdgeInsets.fromLTRB(20, MediaQuery.of(context).padding.top + 12, 20, 16),
      decoration: BoxDecoration(
        color: primary,
        boxShadow: [BoxShadow(color: primary.withOpacity(0.3), blurRadius: 12, offset: Offset(0, 4))],
      ),
      child: Column(children: [
        Row(children: [
          GestureDetector(
            onTap: _goBack,
            child: Container(width: 36, height: 36, decoration: BoxDecoration(color: AppColors.card.withOpacity(0.2), borderRadius: BorderRadius.circular(10)), child: Icon(Icons.arrow_back_rounded, color: AppColors.card, size: 20)),
          ),
          SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(steps[_currentStep], style: TextStyle(color: AppColors.card, fontSize: 17, fontWeight: FontWeight.w800, letterSpacing: -0.3)),
            Text('Step ${_currentStep + 1} of $_totalSteps', style: TextStyle(color: AppColors.card.withOpacity(0.7), fontSize: 12)),
          ])),
        ]),
        SizedBox(height: 14),
        // Progress bar
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: LinearProgressIndicator(
            value: (_currentStep + 1) / _totalSteps,
            minHeight: 6,
            backgroundColor: AppColors.card.withOpacity(0.2),
            valueColor: AlwaysStoppedAnimation<Color>(AppColors.card),
          ),
        ),
        SizedBox(height: 10),
        // Step dots
        Row(mainAxisAlignment: MainAxisAlignment.center, children: List.generate(_totalSteps, (i) {
          final done = i < _currentStep;
          final active = i == _currentStep;
          return AnimatedContainer(
            duration: Duration(milliseconds: 200),
            margin: EdgeInsets.symmetric(horizontal: 3),
            width: active ? 22 : 8, height: 8,
            decoration: BoxDecoration(
              color: done ? AppColors.card : active ? AppColors.card : AppColors.card.withOpacity(0.3),
              borderRadius: BorderRadius.circular(4),
            ),
          );
        })),
      ]),
    );
  }

  Widget _buildBottomNav(Color primary, List<String> steps) {
    final isLast = _currentStep == _totalSteps - 1;
    return Container(
      padding: EdgeInsets.fromLTRB(20, 14, 20, MediaQuery.of(context).padding.bottom + 14),
      decoration: BoxDecoration(color: AppColors.card, border: Border(top: BorderSide(color: AppColors.border))),
      child: Row(children: [
        if (_currentStep > 0)
          Expanded(
            flex: 1,
            child: OutlinedButton(
              onPressed: _goBack,
              style: OutlinedButton.styleFrom(side: BorderSide(color: primary), padding: EdgeInsets.symmetric(vertical: 15), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
              child: Text('Back', style: TextStyle(color: primary, fontWeight: FontWeight.w700)),
            ),
          ),
        if (_currentStep > 0) SizedBox(width: 12),
        Expanded(
          flex: 2,
          child: ElevatedButton(
            onPressed: _isSubmitting ? null : () {
              if (!_validateStep()) {
                _showSnack(_currentStep == 4 ? 'Please upload all required documents' : 'Please fill in all required fields');
                return;
              }
              if (isLast) { _submit(); } else { _goNext(); }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: primary,
              disabledBackgroundColor: primary.withOpacity(0.5),
              padding: EdgeInsets.symmetric(vertical: 15),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: _isSubmitting
                ? SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: AppColors.card, strokeWidth: 2.5))
                : Text(isLast ? 'Submit Application' : 'Continue →', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
          ),
        ),
      ]),
    );
  }

  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  // STEP 1 — Loan Setup
  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Widget _buildStep1(Color primary) {
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _sectionLabel('Select Loan Product'),
        SizedBox(height: 12),
        if (_isLoadingProducts)
          Center(child: CircularProgressIndicator())
        else if (_products.isEmpty)
          Text('No loan products available for this tenant.')
        else
          Column(children: _products.asMap().entries.map((e) => _productCard(e.key, e.value, primary)).toList()),
        SizedBox(height: 24),
        if (!_isLoadingProducts && _products.isNotEmpty) ...[
          _sectionLabel('Loan Amount'),
          SizedBox(height: 12),
          _amountDisplay(primary),
          SizedBox(height: 6),
          SliderTheme(
            data: SliderTheme.of(context).copyWith(activeTrackColor: primary, inactiveTrackColor: AppColors.card, thumbColor: primary, overlayColor: primary.withOpacity(0.15), trackHeight: 5),
            child: Slider(value: _loanAmount, min: (_product['min'] as num).toDouble(), max: (_product['max'] as num).toDouble(), onChanged: (v) => setState(() => _loanAmount = v)),
          ),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Min: ₱${(_product['min'] as num).toInt()}', style: TextStyle(fontSize: 11, color: AppColors.textMuted)),
            Text('Max: ₱${(_product['max'] as num).toInt()}', style: TextStyle(fontSize: 11, color: AppColors.textMuted)),
          ]),
          SizedBox(height: 24),
          _sectionLabel('Loan Term (months)'),
          SizedBox(height: 12),
          _termSelector(primary),
          SizedBox(height: 24),
          _sectionLabel('Loan Purpose (Optional)'),
          SizedBox(height: 12),
          TextFormField(controller: _purposeCtrl, maxLines: 2, decoration: InputDecoration(hintText: 'Briefly describe your loan purpose...')),
          SizedBox(height: 24),
          _summaryCard(primary),
        ],
      ]),
    );
  }

  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  // STEP 2 — Personal Info
  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Widget _buildStep2(Color primary) {
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _formCard([
          _sectionLabel('Contact Details'),
          SizedBox(height: 14),
          _inputField('Email Address *', _emailCtrl, icon: Icons.mail_outline_rounded, keyboard: TextInputType.emailAddress),
          SizedBox(height: 12),
          _inputField('Mobile Number *', _phoneCtrl, icon: Icons.phone_outlined, keyboard: TextInputType.phone),
          SizedBox(height: 12),
          _inputField('Date of Birth (YYYY-MM-DD)', _dobCtrl, icon: Icons.cake_outlined, keyboard: TextInputType.datetime),
        ]),
        SizedBox(height: 16),
        _formCard([
          _sectionLabel('Personal Details'),
          SizedBox(height: 14),
          _dropdownField('Gender', _gender, ['Male', 'Female', 'Other'], (v) => setState(() => _gender = v!), icon: Icons.wc_outlined),
          SizedBox(height: 12),
          _dropdownField('Civil Status', _civilStatus, ['Single', 'Married', 'Widowed', 'Divorced', 'Separated'], (v) => setState(() => _civilStatus = v!), icon: Icons.people_outline_rounded),
        ]),
        SizedBox(height: 16),
        _formCard([
          _sectionLabel('Employment & Income'),
          SizedBox(height: 14),
          _dropdownField('Employment Status', _employmentStatus, ['Employed', 'Self-Employed', 'Unemployed', 'Retired'], (v) => setState(() => _employmentStatus = v!), icon: Icons.work_outline_rounded),
          SizedBox(height: 12),
          _inputField('Occupation / Job Title', _occupationCtrl, icon: Icons.badge_outlined),
          SizedBox(height: 12),
          _inputField('Employer / Business Name', _employerCtrl, icon: Icons.business_outlined),
          SizedBox(height: 12),
          _inputField('Employer Contact Number', _employerContactCtrl, icon: Icons.phone_outlined, keyboard: TextInputType.phone),
          SizedBox(height: 12),
          _inputField('Monthly Income (₱) *', _monthlyIncomeCtrl, icon: Icons.payments_outlined, keyboard: TextInputType.number),
        ]),
      ]),
    );
  }

  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  // STEP 3 — Address
  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Widget _buildStep3(Color primary) {
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _formCard([
          Row(children: [
            Container(width: 36, height: 36, decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(Icons.home_outlined, color: primary, size: 18)),
            SizedBox(width: 10),
            _sectionLabel('Present Address'),
          ]),
          SizedBox(height: 14),
          Row(children: [
            Expanded(child: _inputField('House/Unit No.', _houseNoCtrl)),
            SizedBox(width: 10),
            Expanded(child: _inputField('Street', _streetCtrl)),
          ]),
          SizedBox(height: 12),
          _inputField('Barangay *', _barangayCtrl, icon: Icons.location_on_outlined),
          SizedBox(height: 12),
          Row(children: [
            Expanded(child: _inputField('City / Municipality *', _cityCtrl)),
            SizedBox(width: 10),
            Expanded(child: _inputField('Province *', _provinceCtrl)),
          ]),
          SizedBox(height: 12),
          _inputField('Postal Code', _postalCtrl, keyboard: TextInputType.number),
        ]),
        SizedBox(height: 16),
        _formCard([
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Row(children: [
              Container(width: 36, height: 36, decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(10)), child: Icon(Icons.house_outlined, color: primary, size: 18)),
              SizedBox(width: 10),
              _sectionLabel('Permanent Address'),
            ]),
            Row(children: [
              Text('Same as\nPresent', style: TextStyle(fontSize: 10, color: AppColors.textMuted), textAlign: TextAlign.right),
              SizedBox(width: 4),
              Switch.adaptive(value: _sameAsPermanent, onChanged: (v) => setState(() => _sameAsPermanent = v), activeColor: primary),
            ]),
          ]),
          if (!_sameAsPermanent) ...[
            SizedBox(height: 14),
            Row(children: [
              Expanded(child: _inputField('House/Unit No.', _permHouseCtrl)),
              SizedBox(width: 10),
              Expanded(child: _inputField('Street', _permStreetCtrl)),
            ]),
            SizedBox(height: 12),
            _inputField('Barangay', _permBarangayCtrl, icon: Icons.location_on_outlined),
            SizedBox(height: 12),
            Row(children: [
              Expanded(child: _inputField('City / Municipality', _permCityCtrl)),
              SizedBox(width: 10),
              Expanded(child: _inputField('Province', _permProvinceCtrl)),
            ]),
            SizedBox(height: 12),
            _inputField('Postal Code', _permPostalCtrl, keyboard: TextInputType.number),
          ] else ...[
            SizedBox(height: 12),
            Container(
              padding: EdgeInsets.all(12),
              decoration: BoxDecoration(color: primary.withOpacity(0.06), borderRadius: BorderRadius.circular(10)),
              child: Row(children: [Icon(Icons.check_circle_outline_rounded, color: primary, size: 18), SizedBox(width: 8), Expanded(child: Text('Permanent address is same as present address', style: TextStyle(fontSize: 13, color: primary)))]),
            ),
          ],
        ]),
      ]),
    );
  }

  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  // STEP 4 — Co-maker
  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Widget _buildStep4(Color primary) {
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _formCard([
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              _sectionLabel('Co-maker'),
              SizedBox(height: 4),
              Text('A co-maker guarantees your loan', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
            ]),
            Switch.adaptive(value: _hasComaker, onChanged: (v) => setState(() => _hasComaker = v), activeColor: primary),
          ]),
          if (!_hasComaker) ...[
            SizedBox(height: 16),
            Container(
              padding: EdgeInsets.all(14),
              decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(12)),
              child: Row(children: [
                Icon(Icons.info_outline_rounded, size: 18, color: AppColors.textMuted),
                SizedBox(width: 10),
                Expanded(child: Text('A co-maker is optional but may improve loan approval chances.', style: TextStyle(fontSize: 12, color: AppColors.textMuted))),
              ]),
            ),
          ],
        ]),
        if (_hasComaker) ...[
          SizedBox(height: 16),
          _formCard([
            _sectionLabel('Co-maker Information'),
            SizedBox(height: 14),
            _inputField('Full Name *', _comakerNameCtrl, icon: Icons.person_outline_rounded),
            SizedBox(height: 12),
            _inputField('Relationship to Applicant *', _comakerRelCtrl, icon: Icons.family_restroom_outlined),
            SizedBox(height: 12),
            _inputField('Contact Number *', _comakerContactCtrl, icon: Icons.phone_outlined, keyboard: TextInputType.phone),
            SizedBox(height: 12),
            _inputField('Monthly Income (₱)', _comakerIncomeCtrl, icon: Icons.payments_outlined, keyboard: TextInputType.number),
            SizedBox(height: 12),
            _inputField('Complete Address', _comakerAddressCtrl, icon: Icons.location_on_outlined, maxLines: 2),
          ]),
        ],
      ]),
    );
  }

  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  // STEP 5 — Documents
  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Widget _buildStep5(Color primary) {
    int uploaded = _docTypes.where((d) => _selectedDocs[int.tryParse(d['document_type_id'].toString())] != null).length;
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(
          padding: EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: [primary.withOpacity(0.08), primary.withOpacity(0.02)]),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: primary.withOpacity(0.2)),
          ),
          child: Row(children: [
            Container(width: 48, height: 48, decoration: BoxDecoration(color: primary.withOpacity(0.12), borderRadius: BorderRadius.circular(14)), child: Icon(Icons.folder_copy_outlined, color: primary, size: 24)),
            SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('$uploaded / ${_docTypes.length} documents uploaded', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: primary)),
              SizedBox(height: 2),
              Text('Required fields marked with *', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
            ])),
          ]),
        ),
        SizedBox(height: 16),
        if (_isLoadingDocs)
          Center(child: CircularProgressIndicator())
        else if (_docTypes.isEmpty)
          Text('No documents required.')
        else
          ..._docTypes.map((d) => _docPicker(d, primary)),
      ]),
    );
  }

  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  // STEP 6 — Review
  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Widget _buildStep6(Color primary) {
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Loan Summary
        Container(
          padding: EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: [primary, Color.lerp(primary, activeTenant.value.themeSecondaryColor, 0.35)!], begin: Alignment.topCenter, end: Alignment.bottomCenter),
            borderRadius: BorderRadius.circular(20),
            boxShadow: AppColors.cardShadow,
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Loan Summary', style: TextStyle(color: AppColors.textMuted, fontSize: 12, fontWeight: FontWeight.w600, letterSpacing: 0.5)),
            SizedBox(height: 10),
            Text(_product.isNotEmpty ? (_product['name'] ?? '') : '', style: TextStyle(color: AppColors.card, fontSize: 18, fontWeight: FontWeight.w800)),
            SizedBox(height: 6),
            Text('₱${_loanAmount.toStringAsFixed(2)} • $_selectedTerm months', style: TextStyle(color: AppColors.card.withOpacity(0.8), fontSize: 14)),
            SizedBox(height: 16),
            Row(children: [
              Expanded(child: _reviewStat('Monthly', '₱${_monthly.toStringAsFixed(2)}')),
              Container(width: 1, height: 28, color: AppColors.card.withOpacity(0.24)),
              Expanded(child: _reviewStat('Interest', '${_rate * 100 ~/ 1}%/mo')),
              Container(width: 1, height: 28, color: AppColors.card.withOpacity(0.24)),
              Expanded(child: _reviewStat('Total', '₱${(_monthly * _selectedTerm).toStringAsFixed(2)}')),
            ]),
          ]),
        ),
        SizedBox(height: 16),
        _reviewCard('Personal Info', Icons.person_outline_rounded, primary, [
          _reviewRow('Email', _emailCtrl.text),
          _reviewRow('Phone', _phoneCtrl.text),
          _reviewRow('Date of Birth', _dobCtrl.text),
          _reviewRow('Gender', _gender),
          _reviewRow('Civil Status', _civilStatus),
          _reviewRow('Employment', _employmentStatus),
          if (_monthlyIncomeCtrl.text.isNotEmpty) _reviewRow('Monthly Income', '₱${_monthlyIncomeCtrl.text}'),
        ]),
        SizedBox(height: 12),
        _reviewCard('Address', Icons.home_outlined, primary, [
          _reviewRow('Barangay', _barangayCtrl.text),
          _reviewRow('City', _cityCtrl.text),
          _reviewRow('Province', _provinceCtrl.text),
          _reviewRow('Permanent Address', _sameAsPermanent ? 'Same as present' : _permCityCtrl.text),
        ]),
        SizedBox(height: 12),
        _reviewCard('Co-maker', Icons.people_outline_rounded, primary, [
          _reviewRow('Has Co-maker', _hasComaker ? 'Yes' : 'No'),
          if (_hasComaker) ...[
            _reviewRow('Name', _comakerNameCtrl.text),
            _reviewRow('Relationship', _comakerRelCtrl.text),
            _reviewRow('Contact', _comakerContactCtrl.text),
          ],
        ]),
        SizedBox(height: 12),
        _reviewCard('Documents', Icons.folder_copy_outlined, primary, [
          _reviewRow('Uploaded', '${_selectedDocs.values.where((v) => v != null).length} / ${_docTypes.length} documents'),
        ]),
        SizedBox(height: 16),
        Container(
          padding: EdgeInsets.all(14),
          decoration: BoxDecoration(color: AppColors.bg, borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.secondary.withOpacity(0.3))),
          child: Row(children: [
            Icon(Icons.info_outline_rounded, color: AppColors.secondary, size: 18),
            SizedBox(width: 10),
            Expanded(child: Text('By submitting, you confirm that all information is accurate and complete.', style: TextStyle(fontSize: 12, color: AppColors.textMain, height: 1.5))),
          ]),
        ),
      ]),
    );
  }

  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  // Shared Widgets
  // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Widget _sectionLabel(String t) => Text(t, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textMain));

  Widget _formCard(List<Widget> children) => Container(
    padding: EdgeInsets.all(18),
    decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(20), border: Border.all(color: AppColors.border), boxShadow: AppColors.cardShadow),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: children),
  );

  Widget _inputField(String label, TextEditingController ctrl, {IconData? icon, TextInputType keyboard = TextInputType.text, int maxLines = 1}) {
    return TextFormField(
      controller: ctrl,
      keyboardType: keyboard,
      maxLines: maxLines,
      style: TextStyle(fontSize: 14, color: AppColors.textMain),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: TextStyle(fontSize: 13, color: AppColors.textMuted),
        prefixIcon: icon != null ? Icon(icon, size: 18, color: AppColors.textMuted) : null,
      ),
    );
  }

  Widget _dropdownField(String label, String value, List<String> items, void Function(String?) onChanged, {IconData? icon}) {
    return DropdownButtonFormField<String>(
      initialValue: value,
      onChanged: onChanged,
      style: TextStyle(fontSize: 14, color: AppColors.textMain),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: TextStyle(fontSize: 13, color: AppColors.textMuted),
        prefixIcon: icon != null ? Icon(icon, size: 18, color: AppColors.textMuted) : null,
      ),
      items: items.map((e) => DropdownMenuItem(value: e, child: Text(e))).toList(),
    );
  }

  Widget _productCard(int i, Map<String, dynamic> p, Color primary) {
    final sel = _selectedProduct == i;
    return GestureDetector(
      onTap: () => setState(() {
        _selectedProduct = i;
        _loanAmount = ((p['min'] as num) + (p['max'] as num)) / 2;
        final t = _terms;
        if (t.isNotEmpty && !t.contains(_selectedTerm)) _selectedTerm = t.first;
      }),
      child: AnimatedContainer(
        duration: Duration(milliseconds: 180),
        margin: EdgeInsets.only(bottom: 10),
        padding: EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: sel ? primary.withOpacity(0.06) : AppColors.card,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: sel ? primary : AppColors.border, width: sel ? 2 : 1),
          boxShadow: AppColors.cardShadow,
        ),
        child: Row(children: [
          Container(width: 44, height: 44, decoration: BoxDecoration(color: sel ? primary.withOpacity(0.12) : AppColors.card, borderRadius: BorderRadius.circular(12)),
              child: Icon(p['icon'] as IconData, color: sel ? primary : AppColors.textMuted, size: 22)),
          SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(p['name'] as String, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: sel ? primary : AppColors.textMain)),
            Text('${p['rate']}% / month  •  Up to ₱${((p['max'] as num) / 1000).toInt()}K', style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
          ])),
          Container(width: 22, height: 22, decoration: BoxDecoration(color: sel ? primary : Colors.transparent, shape: BoxShape.circle, border: Border.all(color: sel ? primary : AppColors.border, width: 2)),
              child: sel ? Icon(Icons.check, color: AppColors.card, size: 14) : null),
        ]),
      ),
    );
  }

  Widget _amountDisplay(Color primary) => Container(
    padding: EdgeInsets.symmetric(horizontal: 18, vertical: 16),
    decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(12), border: Border.all(color: primary, width: 2), boxShadow: AppColors.cardShadow),
    child: Row(children: [
      Text('₱', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: primary)),
      SizedBox(width: 8),
      Expanded(child: Text(_loanAmount.toStringAsFixed(2).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (m) => '${m[1]},'),
          style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: primary, letterSpacing: -0.5))),
      Icon(Icons.edit_outlined, color: primary, size: 18),
    ]),
  );

  Widget _termSelector(Color primary) => Row(
    children: _terms.asMap().entries.map((e) {
      final sel = _selectedTerm == e.value;
      return Expanded(child: GestureDetector(
        onTap: () => setState(() => _selectedTerm = e.value),
        child: AnimatedContainer(
          duration: Duration(milliseconds: 180),
          margin: EdgeInsets.only(right: e.key < _terms.length - 1 ? 8 : 0),
          padding: EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(color: sel ? primary : AppColors.card, borderRadius: BorderRadius.circular(12), border: Border.all(color: sel ? primary : AppColors.border, width: sel ? 2 : 1)),
          child: Column(children: [
            Text('${e.value}', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: sel ? AppColors.card : AppColors.textMain)),
            Text('mo', style: TextStyle(fontSize: 10, color: sel ? AppColors.textMuted : AppColors.textMuted)),
          ]),
        ),
      ));
    }).toList(),
  );

  Widget _summaryCard(Color primary) => Container(
    padding: EdgeInsets.all(20),
    decoration: BoxDecoration(color: primary.withOpacity(0.05), borderRadius: BorderRadius.circular(20), border: Border.all(color: primary.withOpacity(0.2), width: 1.5)),
    child: Column(children: [
      Row(children: [Icon(Icons.receipt_long_outlined, color: primary, size: 18), SizedBox(width: 8), Text('Loan Summary', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: primary))]),
      SizedBox(height: 16),
      _summaryRow('Principal Amount', '₱${_loanAmount.toStringAsFixed(2)}', false, primary),
      SizedBox(height: 10),
      _summaryRow('Interest Rate', '${_product['rate']}% / month', false, primary),
      SizedBox(height: 10),
      _summaryRow('Loan Term', '$_selectedTerm months', false, primary),
      SizedBox(height: 10),
      _summaryRow('Total Interest', '₱${_totalInterest.toStringAsFixed(2)}', false, primary),
      Divider(height: 24, color: primary.withOpacity(0.2)),
      _summaryRow('Monthly Payment', '₱${_monthly.toStringAsFixed(2)}', true, primary),
      SizedBox(height: 8),
      _summaryRow('Total Amount', '₱${(_monthly * _selectedTerm).toStringAsFixed(2)}', true, primary),
    ]),
  );

  Widget _summaryRow(String label, String val, bool highlight, Color primary) => Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    children: [
      Text(label, style: TextStyle(fontSize: highlight ? 14 : 13, fontWeight: highlight ? FontWeight.w700 : FontWeight.w500, color: highlight ? AppColors.textMain : AppColors.textMuted)),
      Text(val, style: TextStyle(fontSize: highlight ? 16 : 14, fontWeight: FontWeight.w800, color: highlight ? primary : AppColors.textMain)),
    ],
  );

  Widget _docPicker(dynamic d, Color primary) {
    final id = int.tryParse(d['document_type_id'].toString()) ?? 0;
    final isDone = _selectedDocs[id] != null;
    final isReq = d['is_required'] == '1';
    return Container(
      margin: EdgeInsets.only(bottom: 12),
      padding: EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDone ? AppColors.bg : AppColors.card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDone ? AppColors.primary.withOpacity(0.3) : AppColors.border),
      ),
      child: Row(children: [
        Container(width: 36, height: 36, decoration: BoxDecoration(color: isDone ? AppColors.primary.withOpacity(0.15) : AppColors.card, borderRadius: BorderRadius.circular(10)),
            child: Icon(isDone ? Icons.check_circle_rounded : Icons.description_outlined, color: isDone ? AppColors.primary : AppColors.textMuted, size: 20)),
        SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Expanded(child: Text(d['document_name'], style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textMain))),
            if (isReq && !isDone) Text(' *', style: TextStyle(color: AppColors.secondary, fontSize: 13, fontWeight: FontWeight.w800)),
          ]),
          Text(d['description'], style: TextStyle(fontSize: 11, color: AppColors.textMuted)),
        ])),
        SizedBox(width: 10),
        InkWell(
          onTap: () => setState(() {
            if (isDone) {
              _selectedDocs.remove(id);
            } else {
              _selectedDocs[id] = 'uploads/mock_${d['document_name'].toString().toLowerCase().replaceAll(' ', '_')}.pdf';
            }
          }),
          child: Container(
            padding: EdgeInsets.symmetric(horizontal: 12, vertical: 7),
            decoration: BoxDecoration(color: isDone ? AppColors.primary.withOpacity(0.12) : primary.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
            child: Text(isDone ? '✓ Done' : 'Upload', style: TextStyle(color: isDone ? AppColors.primary : primary, fontSize: 12, fontWeight: FontWeight.w700)),
          ),
        ),
      ]),
    );
  }

  Widget _reviewStat(String label, String value) => Padding(
    padding: EdgeInsets.symmetric(horizontal: 8),
    child: Column(children: [
      Text(value, style: TextStyle(color: AppColors.card, fontSize: 14, fontWeight: FontWeight.w800)),
      SizedBox(height: 2),
      Text(label, style: TextStyle(color: AppColors.card.withOpacity(0.65), fontSize: 10)),
    ]),
  );

  Widget _reviewCard(String title, IconData icon, Color primary, List<Widget> children) => Container(
    padding: EdgeInsets.all(16),
    decoration: BoxDecoration(color: AppColors.card, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.border), boxShadow: AppColors.cardShadow),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Container(width: 32, height: 32, decoration: BoxDecoration(color: primary.withOpacity(0.1), borderRadius: BorderRadius.circular(8)), child: Icon(icon, color: primary, size: 16)),
        SizedBox(width: 10),
        Text(title, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.textMain)),
      ]),
      SizedBox(height: 12),
      Divider(height: 1, color: AppColors.border),
      SizedBox(height: 10),
      ...children,
    ]),
  );

  Widget _reviewRow(String label, String value) => Padding(
    padding: EdgeInsets.only(bottom: 8),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
      SizedBox(width: 16),
      Flexible(child: Text(value.isEmpty ? '—' : value, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textMain), textAlign: TextAlign.right)),
    ]),
  );
}


