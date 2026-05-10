import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';

class ManageProfileScreen extends StatefulWidget {
  const ManageProfileScreen({super.key});

  @override
  State<ManageProfileScreen> createState() => _ManageProfileScreenState();
}

class _ManageProfileScreenState extends State<ManageProfileScreen> {
  bool _isLoading = true;
  bool _isSaving = false;

  // Personal Info
  final _emailCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _dobCtrl = TextEditingController();
  String _gender = 'Male';
  String _civilStatus = 'Single';
  String _employmentStatus = 'Full Time';
  final _occupationCtrl = TextEditingController();
  final _employerCtrl = TextEditingController();
  final _employerContactCtrl = TextEditingController();
  final _monthlyIncomeCtrl = TextEditingController();
  List<String> _allowedEmploymentStatuses = ['Full Time', 'Part Time', 'Contract', 'Self Employed'];

  // Address
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

  // Documents
  List<dynamic> _docTypes = []; // Available KYC doc types
  final Map<int, String?> _selectedDocs = {}; // doc_type_id -> file path

  @override
  void initState() {
    super.initState();
    _fetchFullProfile();
    _fetchTenantConfig();
  }

  Future<void> _fetchTenantConfig() async {
    try {
      final tId = activeTenant.value.id;
      final url = Uri.parse(ApiConfig.getUrl('api_get_tenant_config.php?tenant_id=$tId'));
      final resp = await http.get(url);
      if (resp.statusCode == 200) {
        final data = jsonDecode(resp.body);
        if (data['success'] == true) {
          List<String>? newEmpList;
          final policy = data['policy'];
          if (policy != null && policy['decision_rules'] != null) {
            final empList = policy['decision_rules']['demographics']?['eligible_statuses'];
            if (empList is List) {
              newEmpList = empList.map((e) => e.toString()).toList();
            }
          } else if (data['allowed_employment_statuses'] != null) {
            newEmpList = (data['allowed_employment_statuses'] as List).map((e) => e.toString()).toList();
          }

          if (mounted && newEmpList != null && newEmpList.isNotEmpty) {
            setState(() {
              _allowedEmploymentStatuses = newEmpList!;
              if (!_allowedEmploymentStatuses.contains(_employmentStatus)) {
                _employmentStatus = _allowedEmploymentStatuses.first;
              }
            });
          }
        }
      }
    } catch (_) {}
  }

  Future<void> _fetchFullProfile() async {
    if (currentUser.value == null) return;
    try {
      final uId = currentUser.value!['user_id'];
      final tId = activeTenant.value.id;
      final url = Uri.parse(ApiConfig.getUrl('api_get_full_profile.php?user_id=$uId&tenant_id=$tId'));
      final resp = await http.get(url);
      final data = jsonDecode(resp.body);

      if (data['success'] == true) {
        final profile = data['profile'];

        _emailCtrl.text = profile['email'] ?? '';
        _phoneCtrl.text = profile['phone_number'] ?? '';
        _dobCtrl.text = profile['date_of_birth'] ?? '';
        _gender = profile['gender'] ?? 'Male';
        _civilStatus = profile['civil_status'] ?? 'Single';
        _employmentStatus = profile['employment_status'] ?? 'Employed';
        _occupationCtrl.text = profile['occupation'] ?? '';
        _employerCtrl.text = profile['employer_name'] ?? '';
        _employerContactCtrl.text = profile['employer_contact'] ?? '';
        _monthlyIncomeCtrl.text = (profile['monthly_income'] ?? 0).toString();

        _houseNoCtrl.text = profile['present_house_no'] ?? '';
        _streetCtrl.text = profile['present_street'] ?? '';
        _barangayCtrl.text = profile['present_barangay'] ?? '';
        _cityCtrl.text = profile['present_city'] ?? '';
        _provinceCtrl.text = profile['present_province'] ?? '';
        _postalCtrl.text = profile['present_postal_code'] ?? '';

        _sameAsPermanent = profile['same_as_present'] ?? false;
        _permHouseCtrl.text = profile['permanent_house_no'] ?? '';
        _permStreetCtrl.text = profile['permanent_street'] ?? '';
        _permBarangayCtrl.text = profile['permanent_barangay'] ?? '';
        _permCityCtrl.text = profile['permanent_city'] ?? '';
        _permProvinceCtrl.text = profile['permanent_province'] ?? '';
        _permPostalCtrl.text = profile['permanent_postal_code'] ?? '';

        final docs = profile['documents'] as List;
        _docTypes = docs;

        for (var doc in docs) {
          final id = int.tryParse(doc['document_type_id'].toString()) ?? 0;
          if (doc['file_path'] != null) {
            _selectedDocs[id] = doc['file_path'];
          }
        }
      }
    } catch (_) {}

    setState(() {
      _isLoading = false;
    });
  }

  Future<void> _saveChanges() async {
    HapticFeedback.mediumImpact();
    setState(() => _isSaving = true);
    try {
      final uId = currentUser.value!['user_id'];
      final tId = activeTenant.value.id;
      
      final docs = <Map<String, dynamic>>[];
      _selectedDocs.forEach((id, path) {
        if (path != null) {
          docs.add({
            'document_type_id': id,
            'file_name': path.split('/').last,
            'file_path': path,
          });
        }
      });

      final body = jsonEncode({
        'user_id': uId,
        'tenant_id': tId,
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
        // Docs
        'documents': docs,
      });

      final resp = await http.post(
        Uri.parse(ApiConfig.getUrl('api_update_profile.php')),
        headers: {'Content-Type': 'application/json'},
        body: body,
      );

      final data = jsonDecode(resp.body);
      if (data['success'] == true) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Profile updated successfully'), backgroundColor: Colors.green));
        Navigator.pop(context);
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(data['message'] ?? 'Save failed')));
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e')));
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final primary = activeTenant.value.themePrimaryColor;

    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        backgroundColor: primary,
        title: Text('Manage Profile', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18)),
        iconTheme: IconThemeData(color: Colors.white),
        elevation: 0,
        centerTitle: true,
      ),
      body: _isLoading
          ? Center(child: CircularProgressIndicator(color: primary))
          : SingleChildScrollView(
              padding: EdgeInsets.all(20),
              physics: BouncingScrollPhysics(),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Premium Hero Section
                  Center(
                    child: Column(
                      children: [
                        const SizedBox(height: 10),
                        Hero(
                          tag: 'profile_avatar',
                          child: Container(
                            width: 100, height: 100,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: primary.withOpacity(0.12),
                              border: Border.all(color: primary.withOpacity(0.3), width: 3),
                              boxShadow: [
                                BoxShadow(color: primary.withOpacity(0.15), blurRadius: 30, spreadRadius: 2)
                              ],
                            ),
                            child: Icon(Icons.person_rounded, size: 60, color: primary),
                          ),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          '${currentUser.value?['first_name'] ?? ''} ${currentUser.value?['last_name'] ?? ''}',
                          style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: AppColors.textMain, letterSpacing: -0.5),
                        ),
                        Text(
                          currentUser.value?['email'] ?? '',
                          style: TextStyle(fontSize: 14, color: AppColors.textMuted, fontWeight: FontWeight.w500),
                        ),
                        const SizedBox(height: 32),
                      ],
                    ),
                  ),

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
                    _sectionLabel('Personal Info'),
                    SizedBox(height: 14),
                    _dropdownField('Gender', _gender, ['Male', 'Female'], (v) => setState(() => _gender = v!), icon: Icons.wc_outlined),
                    SizedBox(height: 12),
                    _dropdownField('Civil Status', _civilStatus, ['Single', 'Married', 'Widowed', 'Divorced', 'Separated'], (v) => setState(() => _civilStatus = v!), icon: Icons.people_outline_rounded),
                  ]),
                  SizedBox(height: 16),
                  _formCard([
                    _sectionLabel('Employment & Income'),
                    SizedBox(height: 14),
                    _dropdownField('Employment Status', _employmentStatus, _allowedEmploymentStatuses, (v) => setState(() => _employmentStatus = v!), icon: Icons.work_outline_rounded),
                    SizedBox(height: 12),
                    _inputField('Occupation / Job Title', _occupationCtrl, icon: Icons.badge_outlined),
                    SizedBox(height: 12),
                    _inputField('Employer Name', _employerCtrl, icon: Icons.business_outlined),
                    SizedBox(height: 12),
                    _inputField('Monthly Income (₱)', _monthlyIncomeCtrl, icon: Icons.payments_outlined, keyboard: TextInputType.number),
                  ]),
                  SizedBox(height: 16),
                  _formCard([
                    _sectionLabel('Present Address'),
                    SizedBox(height: 14),
                    Row(children: [
                      Expanded(child: _inputField('House No.', _houseNoCtrl)),
                      SizedBox(width: 10),
                      Expanded(child: _inputField('Street', _streetCtrl)),
                    ]),
                    SizedBox(height: 12),
                    _inputField('Barangay', _barangayCtrl, icon: Icons.location_on_outlined),
                    SizedBox(height: 12),
                    Row(children: [
                      Expanded(child: _inputField('City', _cityCtrl)),
                      SizedBox(width: 10),
                      Expanded(child: _inputField('Province', _provinceCtrl)),
                    ]),
                  ]),
                  SizedBox(height: 16),
                  _formCard([
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        _sectionLabel('Permanent Address'),
                        Row(children: [
                          Text('Same as Present', style: TextStyle(fontSize: 10, color: AppColors.textMuted)),
                          Switch.adaptive(value: _sameAsPermanent, onChanged: (v) => setState(() => _sameAsPermanent = v), activeColor: primary),
                        ]),
                      ],
                    ),
                    if (!_sameAsPermanent) ...[
                      SizedBox(height: 14),
                      Row(children: [
                        Expanded(child: _inputField('House No.', _permHouseCtrl)),
                        SizedBox(width: 10),
                        Expanded(child: _inputField('Street', _permStreetCtrl)),
                      ]),
                      SizedBox(height: 12),
                      _inputField('Barangay', _permBarangayCtrl, icon: Icons.location_on_outlined),
                      SizedBox(height: 12),
                      Row(children: [
                        Expanded(child: _inputField('City', _permCityCtrl)),
                        SizedBox(width: 10),
                        Expanded(child: _inputField('Province', _permProvinceCtrl)),
                      ]),
                    ]
                  ]),
                  SizedBox(height: 16),
                  _formCard([
                    _sectionLabel('My Documents'),
                    SizedBox(height: 14),
                    if (_docTypes.isEmpty) Text('No documents registered.', style: TextStyle(color: AppColors.textMuted))
                    else ..._docTypes.map((d) => _docPicker(d, primary)),
                  ]),
                  SizedBox(height: 30),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _isSaving ? null : _saveChanges,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: primary,
                        padding: EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      child: _isSaving
                          ? SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : Text('Save Changes', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Colors.white)),
                    ),
                  ),
                  SizedBox(height: 40),
                ],
              ),
            ),
    );
  }

  Widget _sectionLabel(String t) => Padding(
    padding: const EdgeInsets.only(bottom: 4),
    child: Text(t, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.primary, letterSpacing: -0.3)),
  );

  Widget _formCard(List<Widget> children) => Container(
    margin: const EdgeInsets.only(bottom: 20),
    padding: const EdgeInsets.all(24),
    decoration: AppPremium.cardDecoration(),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: children),
  );

  Widget _inputField(String label, TextEditingController ctrl, {IconData? icon, TextInputType keyboard = TextInputType.text}) {
    return TextFormField(
      controller: ctrl,
      keyboardType: keyboard,
      style: TextStyle(fontSize: 14, color: AppColors.textMain, fontWeight: FontWeight.w600),
      decoration: AppPremium.fieldDecoration(label: label, icon: icon),
    );
  }

  Widget _dropdownField(String label, String value, List<String> items, void Function(String?) onChanged, {IconData? icon}) {
    final activeValue = items.contains(value) ? value : items.first;
    return DropdownButtonFormField<String>(
      value: activeValue,
      onChanged: onChanged,
      style: TextStyle(fontSize: 14, color: AppColors.textMain, fontWeight: FontWeight.w600),
      decoration: AppPremium.fieldDecoration(label: label, icon: icon),
      items: items.map((e) => DropdownMenuItem(
        value: e,
        child: Text(AppFormat.normalizeLabel(e)),
      )).toList(),
    );
  }

  Widget _docPicker(dynamic d, Color primary) {
    final id = int.tryParse(d['document_type_id'].toString()) ?? 0;
    final isDone = _selectedDocs[id] != null;
    return Container(
      margin: EdgeInsets.only(bottom: 12),
      padding: EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDone ? AppColors.bg : AppColors.card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDone ? Colors.green.withOpacity(0.3) : AppColors.border),
      ),
      child: Row(children: [
        Container(
            width: 36, height: 36, decoration: BoxDecoration(color: isDone ? Colors.green.withOpacity(0.15) : AppColors.card, borderRadius: BorderRadius.circular(10)),
            child: Icon(isDone ? Icons.check_circle_rounded : Icons.description_outlined, color: isDone ? Colors.green : AppColors.textMuted, size: 20)),
        SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(d['name'] ?? 'Document', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textMain)),
              Text(_selectedDocs[id] ?? 'Unuploaded', style: TextStyle(fontSize: 10, color: AppColors.textMuted), maxLines: 1, overflow: TextOverflow.ellipsis),
            ],
          ),
        ),
        SizedBox(width: 10),
        InkWell(
          onTap: () => setState(() {
            if (isDone) {
              _selectedDocs.remove(id);
            } else {
              _selectedDocs[id] = 'uploads/mock_${d['name'].toString().toLowerCase().replaceAll(' ', '_')}.pdf';
            }
          }),
          child: Container(
            padding: EdgeInsets.symmetric(horizontal: 12, vertical: 7),
            decoration: BoxDecoration(color: isDone ? Colors.green.withOpacity(0.12) : primary.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
            child: Text(isDone ? 'Update' : 'Upload', style: TextStyle(color: isDone ? Colors.green : primary, fontSize: 12, fontWeight: FontWeight.w700)),
          ),
        ),
      ]),
    );
  }

  @override
  void dispose() {
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _dobCtrl.dispose();
    _occupationCtrl.dispose();
    _employerCtrl.dispose();
    _employerContactCtrl.dispose();
    _monthlyIncomeCtrl.dispose();
    _houseNoCtrl.dispose();
    _streetCtrl.dispose();
    _barangayCtrl.dispose();
    _cityCtrl.dispose();
    _provinceCtrl.dispose();
    _postalCtrl.dispose();
    _permHouseCtrl.dispose();
    _permStreetCtrl.dispose();
    _permBarangayCtrl.dispose();
    _permCityCtrl.dispose();
    _permProvinceCtrl.dispose();
    _permPostalCtrl.dispose();
    super.dispose();
  }
}
