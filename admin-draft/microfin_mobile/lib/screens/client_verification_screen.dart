import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';
import 'package:file_picker/file_picker.dart';

// ─────────────────────────────────────────────────────────────────────────────
//  FLOW  (4 steps, 0-indexed)
//  Step 0 – Scan ID   → uploads photo → Gemini fills name/dob/gender/address
//                       user also enters phone + civil status + employment
//  Step 1 – Co-maker  → optional
//  Step 2 – Documents → upload required docs
//  Step 3 – Review    → final check + submit
// ─────────────────────────────────────────────────────────────────────────────

class ClientVerificationScreen extends StatefulWidget {
  const ClientVerificationScreen({super.key});
  @override
  State<ClientVerificationScreen> createState() =>
      _ClientVerificationScreenState();
}

class _ClientVerificationScreenState extends State<ClientVerificationScreen> {
  final PageController _pageCtrl = PageController();
  int _currentStep = 0;
  final int _totalSteps = 4;
  final List<String> _stepLabels = [
    'Personal Info',
    'Co-maker',
    'Documents',
    'Review',
  ];

  // ── STEP 0: ID scan + contact + personal ──────────────────────────────
  final _phoneCtrl = TextEditingController();
  final _fullNameCtrl = TextEditingController();
  final _dobCtrl = TextEditingController();
  String _gender = 'Male';
  String _civilStatus = 'Single';
  String _employmentStatus = 'Full Time';
  final _occupationCtrl = TextEditingController();
  final _employerCtrl = TextEditingController();
  final _employerContactCtrl = TextEditingController();
  final _monthlyIncomeCtrl = TextEditingController();

  // Present address fields (populated from scan OR manual entry)
  final _houseNoCtrl = TextEditingController();
  final _streetCtrl = TextEditingController();
  final _barangayCtrl = TextEditingController();
  final _cityCtrl = TextEditingController();
  final _provinceCtrl = TextEditingController();
  final _postalCtrl = TextEditingController();

  // Permanent address
  bool _sameAsPermanent = true;
  final _permHouseCtrl = TextEditingController();
  final _permStreetCtrl = TextEditingController();
  final _permBarangayCtrl = TextEditingController();
  final _permCityCtrl = TextEditingController();
  final _permProvinceCtrl = TextEditingController();
  final _permPostalCtrl = TextEditingController();

  // ID verification
  String? _selectedIdentityType;
  final _idNumberCtrl = TextEditingController();
  final _idExpiryCtrl = TextEditingController();
  final _idIssueDateCtrl = TextEditingController();
  final _idPcnCtrl = TextEditingController();
  final _idCrnCtrl = TextEditingController();
  final _idSssNumberCtrl = TextEditingController();
  final _idMidNumberCtrl = TextEditingController();
  final _idProfessionCtrl = TextEditingController();
  final _idRestrictionCtrl = TextEditingController();
  String? _idPath;
  bool _isUploadingId = false;
  bool _showScannedFields = true;

  // ── STEP 1: Co-maker ──────────────────────────────────────────────────
  bool _hasComaker = false;
  final _comakerNameCtrl = TextEditingController();
  final _comakerRelCtrl = TextEditingController();
  final _comakerContactCtrl = TextEditingController();
  final _comakerIncomeCtrl = TextEditingController();
  final _comakerAddressCtrl = TextEditingController();

  // ── STEP 2: Documents ─────────────────────────────────────────────────
  bool _isLoadingDocs = true;
  List<dynamic> _docTypes = [];
  final Map<int, String?> _selectedDocs = {};

  bool _isSubmitting = false;
  List<String> _allowedEmploymentStatuses = [
    'Full Time',
    'Part Time',
    'Contract',
    'Freelancer / Gig',
    'Self Employed',
    'Casual / Seasonal',
    'Retired / Pensioner',
    'Student',
    'Unemployed',
  ];

  // ── ID type definitions ────────────────────────────────────────────────
  static const List<Map<String, dynamic>> _idTypes = [
    {
      'v': 'philsys',
      'l': 'National ID (PhilID/ePhilID)',
      'pcn': true,
      'expiry': false,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'passport',
      'l': 'Passport',
      'pcn': false,
      'expiry': true,
      'issue': true,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'dl',
      'l': "Driver's License",
      'pcn': false,
      'expiry': true,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': true,
    },
    {
      'v': 'umid',
      'l': 'UMID',
      'pcn': false,
      'expiry': false,
      'issue': false,
      'crn': true,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'sss',
      'l': 'SSS ID',
      'pcn': false,
      'expiry': false,
      'issue': false,
      'crn': false,
      'sss': true,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'gsis',
      'l': 'GSIS e-Card',
      'pcn': false,
      'expiry': false,
      'issue': false,
      'crn': true,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'prc',
      'l': 'PRC ID',
      'pcn': false,
      'expiry': true,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': true,
      'rest': false,
    },
    {
      'v': 'postal',
      'l': 'Postal ID',
      'pcn': false,
      'expiry': false,
      'issue': true,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'seaman',
      'l': "Seaman's Book / SIRB",
      'pcn': false,
      'expiry': true,
      'issue': true,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'senior',
      'l': 'Senior Citizen ID',
      'pcn': false,
      'expiry': false,
      'issue': true,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'pwd',
      'l': 'PWD ID',
      'pcn': false,
      'expiry': false,
      'issue': true,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'voter',
      'l': "Voter's ID",
      'pcn': false,
      'expiry': false,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'nbi',
      'l': 'NBI Clearance',
      'pcn': false,
      'expiry': true,
      'issue': true,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'police',
      'l': 'Police Clearance',
      'pcn': false,
      'expiry': true,
      'issue': true,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'tin',
      'l': 'TIN ID',
      'pcn': false,
      'expiry': false,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'school',
      'l': 'School ID',
      'pcn': false,
      'expiry': false,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'company',
      'l': 'Company ID',
      'pcn': false,
      'expiry': false,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'barangay',
      'l': 'Barangay ID',
      'pcn': false,
      'expiry': false,
      'issue': true,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'ofw',
      'l': 'OFW ID',
      'pcn': false,
      'expiry': true,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'owwa',
      'l': 'OWWA ID',
      'pcn': false,
      'expiry': true,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'ibp',
      'l': 'IBP ID',
      'pcn': false,
      'expiry': false,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
    {
      'v': 'govt',
      'l': 'Government Office / GOCC ID',
      'pcn': false,
      'expiry': false,
      'issue': false,
      'crn': false,
      'sss': false,
      'mid': false,
      'prof': false,
      'rest': false,
    },
  ];

  List<Map<String, dynamic>> _availableIdTypes = List.from(_idTypes);

  Map<String, dynamic> get _activeIdType => _availableIdTypes.firstWhere(
    (t) => t['v'] == _selectedIdentityType,
    orElse: () => <String, dynamic>{},
  );

  String _normalizeIdTypeValue(String value) {
    return value
        .toLowerCase()
        .trim()
        .replaceAll(RegExp(r'[^a-z0-9]+'), '_')
        .replaceAll(RegExp(r'^_+|_+$'), '');
  }

  List<Map<String, dynamic>> _dedupeIdTypes(List<Map<String, dynamic>> items) {
    final seen = <String>{};
    final deduped = <Map<String, dynamic>>[];

    for (final item in items) {
      final normalizedValue = _normalizeIdTypeValue(
        (item['v'] ?? '').toString(),
      );
      if (normalizedValue.isEmpty || seen.contains(normalizedValue)) {
        continue;
      }
      seen.add(normalizedValue);
      deduped.add({...item, 'v': normalizedValue});
    }

    return deduped;
  }

  // ── Lifecycle ──────────────────────────────────────────────────────────
  @override
  void initState() {
    super.initState();
    _fetchTenantConfig();
    _fetchDocTypes();
    _prefillFromUser();
  }

  Future<void> _fetchTenantConfig() async {
    final tenantId = currentUser.value?['tenant_id'] ?? activeTenant.value.id;
    try {
      final resp = await http.get(
        Uri.parse(
          ApiConfig.getUrl('api_get_tenant_config.php?tenant_id=$tenantId'),
        ),
      );
      if (resp.statusCode == 200) {
        final data = jsonDecode(resp.body);
        if (data['success'] == true) {
          final policy = data['policy'];
          List<String>? newEmpList;
          List<String>? newIdList;

          if (policy != null) {
            final demog =
                policy['decision_rules']?['decision_rules']?['demographics'];
            final empList =
                demog?['eligible_statuses'] ??
                data['allowed_employment_statuses'];
            if (empList is List) {
              newEmpList = empList.map((e) => e.toString()).toList();
            }

            final compList =
                policy['compliance_documents']?['document_requirements'];
            if (compList is List) {
              final idReq = compList.firstWhere(
                (r) => r['category_key'] == 'identity_document',
                orElse: () => null,
              );
              if (idReq != null && idReq['accepted_document_names'] is List) {
                newIdList = (idReq['accepted_document_names'] as List)
                    .map((e) => e.toString())
                    .toList();
              } else if (idReq != null && idReq['document_options'] is List) {
                // Fallback for nested object arrays if used
                newIdList = (idReq['document_options'] as List)
                    .where((e) => e['is_accepted'] == true)
                    .map((e) => e['document_name'].toString())
                    .toList();
              }
            }
          } else if (data['allowed_employment_statuses'] != null) {
            newEmpList = (data['allowed_employment_statuses'] as List)
                .map((e) => e.toString())
                .toList();
          }

          if (mounted) {
            setState(() {
              if (newEmpList != null && newEmpList.isNotEmpty) {
                _allowedEmploymentStatuses = newEmpList;
                // Normalize selection to the raw key
                if (!_allowedEmploymentStatuses.contains(_employmentStatus)) {
                  _employmentStatus = _allowedEmploymentStatuses.first;
                }
              }
              if (newIdList != null && newIdList.isNotEmpty) {
                // Rebuild the ID array exactly 1:1 with the institutional settings
                _availableIdTypes = _dedupeIdTypes(
                  newIdList!.map((allowedName) {
                    final a = allowedName.toLowerCase();

                    // Map to an existing known type for UI flags if possible
                    final predefinedMatch = _idTypes.firstWhere((t) {
                      final label = (t['l'] as String).toLowerCase();
                      return a.contains(label) ||
                          label.contains(a.split(' ').first);
                    }, orElse: () => <String, dynamic>{});

                    if (predefinedMatch.isNotEmpty) {
                      return {
                        ...predefinedMatch,
                        'l':
                            allowedName, // Override to match institutional labeling exactly
                        'v': allowedName,
                      };
                    } else {
                      // Create a generic fallback for custom defined IDs
                      return {
                        'v': allowedName,
                        'l': allowedName,
                        'pcn': false,
                        'expiry': false,
                        'issue': false,
                        'crn': false,
                        'sss': false,
                        'mid': false,
                        'prof': false,
                        'rest': false,
                      };
                    }
                  }).toList(),
                );

                if (_availableIdTypes.isEmpty) {
                  _availableIdTypes = _dedupeIdTypes(List.from(_idTypes));
                }

                final normalizedSelection = _selectedIdentityType == null
                    ? null
                    : _normalizeIdTypeValue(_selectedIdentityType!);

                // Reset selected ID if it's no longer valid
                if (normalizedSelection != null &&
                    !_availableIdTypes.any(
                      (t) => t['v'] == normalizedSelection,
                    )) {
                  _selectedIdentityType = null;
                } else {
                  _selectedIdentityType = normalizedSelection;
                }
              }
            });
          }
        }
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _pageCtrl.dispose();
    for (final c in [
      _phoneCtrl,
      _fullNameCtrl,
      _dobCtrl,
      _occupationCtrl,
      _employerCtrl,
      _employerContactCtrl,
      _monthlyIncomeCtrl,
      _houseNoCtrl,
      _streetCtrl,
      _barangayCtrl,
      _cityCtrl,
      _provinceCtrl,
      _postalCtrl,
      _permHouseCtrl,
      _permStreetCtrl,
      _permBarangayCtrl,
      _permCityCtrl,
      _permProvinceCtrl,
      _permPostalCtrl,
      _comakerNameCtrl,
      _comakerRelCtrl,
      _comakerContactCtrl,
      _comakerIncomeCtrl,
      _comakerAddressCtrl,
      _idNumberCtrl,
      _idExpiryCtrl,
      _idIssueDateCtrl,
      _idPcnCtrl,
      _idCrnCtrl,
      _idSssNumberCtrl,
      _idMidNumberCtrl,
      _idProfessionCtrl,
      _idRestrictionCtrl,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  // ── Helpers ────────────────────────────────────────────────────────────
  void _prefillFromUser() {
    final u = currentUser.value;
    if (u == null) return;
    _phoneCtrl.text = u['phone_number'] ?? '';

    // Auto-populate full name from registration
    final first = u['first_name'] ?? '';
    final last = u['last_name'] ?? '';
    if (first.isNotEmpty || last.isNotEmpty) {
      _fullNameCtrl.text = '$first $last'.trim();
    }
  }

  void _showSnack(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  Map<String, dynamic> _decodeApiBody(
    String rawBody, {
    String fallbackMessage = 'The server returned an invalid response.',
  }) {
    final body = rawBody.trim();
    if (body.isEmpty) {
      return {'success': false, 'message': fallbackMessage};
    }

    if (body.startsWith('<!doctype html') ||
        body.startsWith('<html') ||
        body.startsWith('<HTML')) {
      return {'success': false, 'message': fallbackMessage};
    }

    try {
      final decoded = jsonDecode(body);
      if (decoded is Map<String, dynamic>) {
        return decoded;
      }
      if (decoded is Map) {
        return decoded.map((key, value) => MapEntry(key.toString(), value));
      }
    } catch (_) {}

    return {'success': false, 'message': fallbackMessage};
  }

  Future<Map<String, dynamic>> _readStreamedJson(
    http.StreamedResponse response, {
    required String fallbackMessage,
  }) async {
    final body = await response.stream.bytesToString();
    final data = _decodeApiBody(body, fallbackMessage: fallbackMessage);
    if (response.statusCode >= 400 && data['success'] != true) {
      return {
        'success': false,
        'message':
            data['message'] ?? '$fallbackMessage (HTTP ${response.statusCode})',
      };
    }
    return data;
  }

  void _goNext() {
    if (_currentStep < _totalSteps - 1) {
      HapticFeedback.lightImpact();
      setState(() => _currentStep++);
      _pageCtrl.animateToPage(
        _currentStep,
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOut,
      );
    }
  }

  void _goBack() {
    if (_currentStep > 0) {
      HapticFeedback.lightImpact();
      setState(() => _currentStep--);
      _pageCtrl.animateToPage(
        _currentStep,
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOut,
      );
    } else {
      Navigator.of(context).pop();
    }
  }

  bool _validateStep() {
    switch (_currentStep) {
      case 0:
        return _phoneCtrl.text.trim().isNotEmpty &&
            _selectedIdentityType != null &&
            _idPath != null &&
            _monthlyIncomeCtrl.text.trim().isNotEmpty;
      case 1:
        if (_hasComaker) {
          return _comakerNameCtrl.text.trim().isNotEmpty &&
              _comakerRelCtrl.text.trim().isNotEmpty &&
              _comakerContactCtrl.text.trim().isNotEmpty;
        }
        return true;
      case 2:
        return true;
      case 3:
        return true;
      default:
        return true;
    }
  }

  String get _effectivePresentAddress => [
    _houseNoCtrl.text,
    _streetCtrl.text,
    _barangayCtrl.text,
    _cityCtrl.text,
    _provinceCtrl.text,
    _postalCtrl.text,
  ].where((s) => s.trim().isNotEmpty).join(', ');

  // ── Clear ID fields on type change ─────────────────────────────────────
  void _clearIdFields() {
    for (final c in [
      _idNumberCtrl,
      _idExpiryCtrl,
      _idIssueDateCtrl,
      _idPcnCtrl,
      _idCrnCtrl,
      _idSssNumberCtrl,
      _idMidNumberCtrl,
      _idProfessionCtrl,
      _idRestrictionCtrl,
      _fullNameCtrl,
      _dobCtrl,
      _houseNoCtrl,
      _streetCtrl,
      _barangayCtrl,
      _cityCtrl,
      _provinceCtrl,
      _postalCtrl,
    ]) {
      c.clear();
    }
    setState(() {
      _idPath = null;
      _showScannedFields = true;
    });
  }

  // ── Upload ID ───────────────────────────────────────────────────
  Future<void> _pickUploadId() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png'],
        withData: true,
      );
      if (result == null || result.files.isEmpty) return;
      final file = result.files.first;
      final bytes = file.bytes;
      if (bytes == null) {
        _showSnack('Cannot read file.');
        return;
      }

      setState(() {
        _isUploadingId = true;
      });

      // 1) Upload for storage
      final clientId =
          int.tryParse('${currentUser.value?['client_id'] ?? 0}') ?? 0;
      var upReq = http.MultipartRequest(
        'POST',
        Uri.parse(ApiConfig.getUrl('api_upload_document.php')),
      );
      upReq.fields['tenant_id'] = activeTenant.value.id;
      upReq.fields['client_id'] = clientId.toString();
      upReq.fields['user_id'] = '${currentUser.value?['user_id'] ?? 0}';
      upReq.fields['file_category'] = 'scanned_id';
      upReq.files.add(
        http.MultipartFile.fromBytes('file', bytes, filename: file.name),
      );
      final upRes = await upReq.send();
      final upJson = await _readStreamedJson(
        upRes,
        fallbackMessage: 'Unable to upload the selected ID image.',
      );
      if (upJson['success'] != true) {
        _showSnack(upJson['message'] ?? 'Upload failed');
        return;
      }

      setState(() {
        _idPath = upJson['file_path'];
        _isUploadingId = false;
        _showScannedFields = true;
      });
    } catch (e) {
      _showSnack('Error: $e');
    } finally {
      if (mounted)
        setState(() {
          _isUploadingId = false;
        });
    }
  }

  // ── Fetch doc types ────────────────────────────────────────────────────
  Future<void> _fetchDocTypes() async {
    try {
      final resp = await http.get(
        Uri.parse(ApiConfig.getUrl('api_get_doc_types.php')),
      );
      final data = _decodeApiBody(
        resp.body,
        fallbackMessage: 'Unable to load document requirements.',
      );
      if (data['success'] == true) {
        setState(() {
          _docTypes = (data['document_types'] as List).where((doc) {
            final name = doc['document_name'].toString().toLowerCase();
            // Only show KYC supporting documents relevant to client verification.
            // ID documents are already captured in Step 0 (scan), so exclude them.
            // Loan-specific docs (school, medical, business, etc.) are excluded too.
            final isKyc =
                name.contains('proof of income') ||
                name.contains('proof of billing') ||
                name.contains('proof of legitimacy') ||
                name.contains('income') ||
                name.contains('billing');
            return doc['is_required'] == '1' && isKyc;
          }).toList();
          _isLoadingDocs = false;
        });
      }
    } catch (_) {
      setState(() => _isLoadingDocs = false);
    }
  }

  Future<void> _pickAndUploadDocument(int docTypeId) async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
        withData: true,
      );
      if (result == null || result.files.isEmpty) return;
      final file = result.files.first;
      setState(() => _isSubmitting = true);

      final clientId =
          int.tryParse('${currentUser.value?['client_id'] ?? 0}') ?? 0;
      var req = http.MultipartRequest(
        'POST',
        Uri.parse(ApiConfig.getUrl('api_upload_document.php')),
      );
      req.fields['tenant_id'] = activeTenant.value.id;
      req.fields['client_id'] = clientId.toString();
      req.fields['user_id'] = '${currentUser.value?['user_id'] ?? 0}';
      req.fields['file_category'] = docTypeId.toString();
      if (file.bytes != null) {
        req.files.add(
          http.MultipartFile.fromBytes(
            'file',
            file.bytes!,
            filename: file.name,
          ),
        );
      } else if (file.path != null) {
        req.files.add(
          await http.MultipartFile.fromPath(
            'file',
            file.path!,
            filename: file.name,
          ),
        );
      } else {
        _showSnack('Cannot pick file.');
        return;
      }

      final res = await req.send();
      final json = await _readStreamedJson(
        res,
        fallbackMessage: 'Unable to upload the selected document.',
      );
      if (json['success'] == true) {
        setState(() => _selectedDocs[docTypeId] = json['file_path']);
        _showSnack('File uploaded successfully');
      } else {
        _showSnack(json['message'] ?? 'Upload failed');
      }
    } catch (e) {
      _showSnack('Error: $e');
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  // ── Submit ─────────────────────────────────────────────────────────────
  Future<void> _submit() async {
    if (!_validateStep()) {
      _showSnack('Please fill in all required fields');
      return;
    }
    setState(() => _isSubmitting = true);
    try {
      final resp = await http.post(
        Uri.parse(ApiConfig.getUrl('api_submit_verification.php')),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'user_id': currentUser.value?['user_id']?.toString() ?? '',
          'tenant_id': activeTenant.value.id,
          'phone_number': _phoneCtrl.text,
          'full_name': _fullNameCtrl.text,
          'date_of_birth': _dobCtrl.text,
          'gender': _gender,
          'civil_status': _civilStatus,
          'employment_status': _employmentStatus,
          'occupation': _occupationCtrl.text,
          'employer': _employerCtrl.text,
          'employer_contact': _employerContactCtrl.text,
          'monthly_income': _monthlyIncomeCtrl.text,
          'present_address': _effectivePresentAddress,
          'house_no': _houseNoCtrl.text,
          'street': _streetCtrl.text,
          'barangay': _barangayCtrl.text,
          'city': _cityCtrl.text,
          'province': _provinceCtrl.text,
          'postal': _postalCtrl.text,
          'same_as_permanent': _sameAsPermanent ? '1' : '0',
          'perm_house_no': _sameAsPermanent
              ? _houseNoCtrl.text
              : _permHouseCtrl.text,
          'perm_street': _sameAsPermanent
              ? _streetCtrl.text
              : _permStreetCtrl.text,
          'perm_barangay': _sameAsPermanent
              ? _barangayCtrl.text
              : _permBarangayCtrl.text,
          'perm_city': _sameAsPermanent ? _cityCtrl.text : _permCityCtrl.text,
          'perm_province': _sameAsPermanent
              ? _provinceCtrl.text
              : _permProvinceCtrl.text,
          'perm_postal': _sameAsPermanent
              ? _postalCtrl.text
              : _permPostalCtrl.text,
          'has_comaker': _hasComaker ? '1' : '0',
          'comaker_name': _comakerNameCtrl.text,
          'comaker_relationship': _comakerRelCtrl.text,
          'comaker_contact': _comakerContactCtrl.text,
          'comaker_income': _comakerIncomeCtrl.text,
          'comaker_address': _comakerAddressCtrl.text,
          'id_type': _selectedIdentityType ?? '',
          'id_number': _idNumberCtrl.text,
          'id_path': _idPath ?? '',
          'id_expiry': _idExpiryCtrl.text,
          'id_issue_date': _idIssueDateCtrl.text,
          'id_pcn': _idPcnCtrl.text,
          'id_crn': _idCrnCtrl.text,
          'id_sss': _idSssNumberCtrl.text,
          'id_mid': _idMidNumberCtrl.text,
          'id_profession': _idProfessionCtrl.text,
          'id_restriction': _idRestrictionCtrl.text,
          'documents': [
            if (_idPath != null && _idPath!.isNotEmpty)
              {
                'document_type_id': 'scanned_id',
                'file_name': 'Scanned_ID',
                'file_path': _idPath,
              },
            ..._selectedDocs.entries.map(
              (e) => {
                'document_type_id': e.key.toString(),
                'file_name': 'Document_${e.key}',
                'file_path': e.value ?? '',
              },
            ),
          ],
        }),
      );
      final json = _decodeApiBody(
        resp.body,
        fallbackMessage: 'Unable to submit the verification form right now.',
      );
      if (json['success'] == true) {
        final current = currentUser.value;
        final nextStatus =
            json['verification_status'] ??
            json['document_verification_status'] ??
            'Pending';

        if (current != null) {
          currentUser.value = {...current, 'verification_status': nextStatus};
        }

        if (mounted) {
          _showSnack('Profile submitted successfully!');
          Navigator.of(context).pop();
        }
      } else {
        _showSnack(json['message'] ?? 'Submission failed.');
      }
    } catch (e) {
      _showSnack('Error: $e');
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  // ══════════════════════════════════════════════════════════════════════
  //  BUILD
  // ══════════════════════════════════════════════════════════════════════
  @override
  Widget build(BuildContext context) {
    final primary = AppColors.primary;
    return Scaffold(
      backgroundColor: const Color(0xFFF3F4F6),
      body: SafeArea(
        child: Column(
          children: [
            _buildHeader(context, primary),
            Expanded(
              child: PageView(
                controller: _pageCtrl,
                physics: const NeverScrollableScrollPhysics(),
                children: [
                  _buildStep0(primary),
                  _buildStep1(primary),
                  _buildStep2(primary),
                  _buildStep3(primary),
                ],
              ),
            ),
            _buildBottomNav(primary),
          ],
        ),
      ),
    );
  }

  // ── Header ─────────────────────────────────────────────────────────────
  Widget _buildHeader(BuildContext context, Color primary) {
    final tenant = activeTenant.value;
    return Container(
      padding: const EdgeInsets.only(bottom: 12),
      decoration: const BoxDecoration(color: Color(0xFFF3F4F6)),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 16),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    GestureDetector(
                      onTap: _goBack,
                      child: Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.black,
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.1),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.arrow_back_rounded,
                          color: Colors.white,
                          size: 20,
                        ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          tenant.appName,
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                            color: activeTenant.value.themePrimaryColor,
                            height: 1.1,
                            letterSpacing: -0.7,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          _stepLabels[_currentStep],
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF111827),
                            height: 1.1,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.05),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.black.withOpacity(0.1)),
                  ),
                  child: Text(
                    '${_currentStep + 1} / $_totalSteps',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                      color: Colors.black,
                    ),
                  ),
                ),
              ],
            ),
          ),
          _buildProgressBar(context, primary),
        ],
      ),
    );
  }

  Widget _buildProgressBar(BuildContext context, Color primary) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Row(
        children: List.generate(_totalSteps, (i) {
          final active = i == _currentStep;
          final done = i < _currentStep;
          return Expanded(
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 300),
              margin: EdgeInsets.only(right: i == _totalSteps - 1 ? 0 : 8),
              height: 6,
              decoration: BoxDecoration(
                color: active || done
                    ? Colors.black
                    : Colors.black.withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
              ),
            ),
          );
        }),
      ),
    );
  }

  // ── Bottom nav ─────────────────────────────────────────────────────────
  Widget _buildBottomNav(Color primary) {
    final isLast = _currentStep == _totalSteps - 1;
    return Container(
      padding: EdgeInsets.fromLTRB(
        20,
        16,
        20,
        MediaQuery.of(context).padding.bottom + 16,
      ),
      decoration: BoxDecoration(
        color: AppColors.card,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 20,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: Row(
        children: [
          if (_currentStep > 0) ...[
            Expanded(
              flex: 1,
              child: OutlinedButton(
                onPressed: _goBack,
                style: OutlinedButton.styleFrom(
                  side: BorderSide(color: AppColors.textMain, width: 2),
                  padding: const EdgeInsets.symmetric(vertical: 18),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppPremium.radius - 8),
                  ),
                ),
                child: Text(
                  'Back',
                  style: TextStyle(
                    color: AppColors.textMain,
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 12),
          ],
          Expanded(
            flex: 2,
            child: Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(AppPremium.radius - 8),
                gradient: LinearGradient(
                  colors: [primary, primary.withOpacity(0.8)],
                ),
                boxShadow: [
                  BoxShadow(
                    color: primary.withOpacity(0.3),
                    blurRadius: 15,
                    offset: const Offset(0, 5),
                  ),
                ],
              ),
              child: ElevatedButton(
                onPressed: _isSubmitting
                    ? null
                    : () {
                        if (!_validateStep()) {
                          _showSnack('Please fill in all required fields');
                          return;
                        }
                        isLast ? _submit() : _goNext();
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.transparent,
                  foregroundColor: Colors.white,
                  shadowColor: Colors.transparent,
                  padding: const EdgeInsets.symmetric(vertical: 18),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppPremium.radius - 8),
                  ),
                ),
                child: _isSubmitting
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          color: Colors.white,
                          strokeWidth: 2.5,
                        ),
                      )
                    : Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            isLast ? 'Submit Profile' : 'Continue',
                            style: const TextStyle(
                              fontWeight: FontWeight.w900,
                              fontSize: 16,
                              letterSpacing: -0.5,
                            ),
                          ),
                          const SizedBox(width: 8),
                          const Icon(Icons.arrow_forward_rounded, size: 20),
                        ],
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ══════════════════════════════════════════════════════════════════════
  //  STEP 0
  // ══════════════════════════════════════════════════════════════════════
  Widget _buildStep0(Color primary) {
    final t = _activeIdType;
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ① ID Type selector
          _infoChip(
            primary,
            Icons.credit_card_rounded,
            'Identity Verification',
            'Select your government-issued ID and upload a photo.',
          ),
          const SizedBox(height: 16),
          _formCard([
            _sectionLabel('Select ID Type *'),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              value: _selectedIdentityType,
              hint: Text(
                'Choose a Government ID',
                style: TextStyle(color: AppColors.textMuted, fontSize: 14),
              ),
              isExpanded: true,
              icon: Icon(Icons.arrow_drop_down_rounded, color: primary),
              decoration: _dropDecor(primary),
              items: _availableIdTypes
                  .map(
                    (idT) => DropdownMenuItem<String>(
                      value: idT['v'] as String,
                      child: Text(
                        idT['l'] as String,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textMain,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  )
                  .toList(),
              onChanged: (val) => setState(() {
                _selectedIdentityType = val;
                _clearIdFields();
              }),
            ),
          ]),

          // ② Upload photo
          if (t.isNotEmpty) ...[
            const SizedBox(height: 16),
            _formCard([
              _sectionLabel('Upload ID Photo *'),
              const SizedBox(height: 16),
              GestureDetector(
                onTap: _isUploadingId ? null : _pickUploadId,
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    vertical: 32,
                    horizontal: 20,
                  ),
                  decoration: BoxDecoration(
                    color: _idPath != null
                        ? primary.withOpacity(0.06)
                        : AppColors.bg.withOpacity(0.4),
                    borderRadius: BorderRadius.circular(AppPremium.radius - 8),
                    border: Border.all(
                      color: _idPath != null ? primary : Colors.transparent,
                      width: 2,
                    ),
                    boxShadow: _idPath != null
                        ? [
                            BoxShadow(
                              color: primary.withOpacity(0.1),
                              blurRadius: 15,
                            ),
                          ]
                        : null,
                  ),
                  child: _isUploadingId
                      ? Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            SizedBox(
                              width: 40,
                              height: 40,
                              child: CircularProgressIndicator(
                                color: primary,
                                strokeWidth: 3,
                              ),
                            ),
                            const SizedBox(height: 18),
                            Text(
                              'Uploading…',
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w900,
                                color: AppColors.textMain,
                                letterSpacing: -0.3,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Please wait while we secure your photo',
                              style: TextStyle(
                                fontSize: 13,
                                color: AppColors.textMuted,
                                fontWeight: FontWeight.w500,
                              ),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        )
                      : Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: _idPath != null
                                    ? primary.withOpacity(0.12)
                                    : AppColors.bg.withOpacity(0.6),
                              ),
                              child: Icon(
                                _idPath != null
                                    ? Icons.verified_user_rounded
                                    : Icons.add_a_photo_rounded,
                                color: _idPath != null
                                    ? primary
                                    : AppColors.textMuted,
                                size: 36,
                              ),
                            ),
                            const SizedBox(height: 14),
                            Text(
                              _idPath != null
                                  ? 'ID UPLOADED ✓'
                                  : 'TAP TO UPLOAD ID',
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w900,
                                color: _idPath != null
                                    ? primary
                                    : AppColors.textMain,
                                letterSpacing: 1.2,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              _idPath != null
                                  ? 'Tap to re-upload if needed'
                                  : 'Upload a clear photo of your ID',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textMuted,
                              ),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                ),
              ),
              // Type-specific extra fields
              const SizedBox(height: 16),
              _inputField(
                'ID Number *',
                _idNumberCtrl,
                icon: Icons.tag_rounded,
              ),
              if (t['pcn'] == true) ...[
                const SizedBox(height: 12),
                _inputField(
                  'PhilSys Card Number (PCN)',
                  _idPcnCtrl,
                  icon: Icons.fingerprint_rounded,
                ),
              ],
              if (t['crn'] == true) ...[
                const SizedBox(height: 12),
                _inputField(
                  'Common Reference Number (CRN)',
                  _idCrnCtrl,
                  icon: Icons.link_rounded,
                ),
              ],
              if (t['sss'] == true) ...[
                const SizedBox(height: 12),
                _inputField(
                  'SSS Number',
                  _idSssNumberCtrl,
                  icon: Icons.numbers_rounded,
                ),
              ],
              if (t['mid'] == true) ...[
                const SizedBox(height: 12),
                _inputField(
                  'Pag-IBIG MID Number',
                  _idMidNumberCtrl,
                  icon: Icons.home_work_outlined,
                ),
              ],
              if (t['prof'] == true) ...[
                const SizedBox(height: 12),
                _inputField(
                  'Profession / Licensure',
                  _idProfessionCtrl,
                  icon: Icons.school_outlined,
                ),
              ],
              if (t['rest'] == true) ...[
                const SizedBox(height: 12),
                _inputField(
                  'Restriction Codes',
                  _idRestrictionCtrl,
                  icon: Icons.drive_eta_outlined,
                ),
              ],
              if (t['expiry'] == true) ...[
                const SizedBox(height: 12),
                _dateTap(
                  _idExpiryCtrl,
                  'Expiry Date',
                  Icons.event_outlined,
                  primary,
                  first: DateTime.now(),
                  last: DateTime(2060),
                  initial: DateTime.now().add(const Duration(days: 365)),
                ),
              ],
              if (t['issue'] == true) ...[
                const SizedBox(height: 12),
                _dateTap(
                  _idIssueDateCtrl,
                  'Issue Date',
                  Icons.calendar_today_outlined,
                  primary,
                  first: DateTime(1990),
                  last: DateTime.now(),
                  initial: DateTime.now(),
                ),
              ],
            ]),
          ],

          // ③ Scanned fields – revealed after successful scan (or fallback to manual)
          if (_showScannedFields) ...[
            const SizedBox(height: 20),
            _infoChip(
              primary,
              Icons.edit_note_rounded,
              'Personal Information',
              'Please enter your personal details below.',
            ),
            const SizedBox(height: 14),

            // Personal
            _formCard([
              _sectionLabel('Personal Information'),
              const SizedBox(height: 14),
              _inputField(
                'Full Name',
                _fullNameCtrl,
                icon: Icons.person_outline_rounded,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _dateTap(
                      _dobCtrl,
                      'Date of Birth',
                      Icons.cake_outlined,
                      primary,
                      first: DateTime(1900),
                      last: DateTime.now(),
                      initial: DateTime.now().subtract(
                        const Duration(days: 365 * 18),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _dropdownField(
                      'Gender',
                      _gender,
                      ['Male', 'Female'],
                      (v) => setState(() => _gender = v!),
                      icon: Icons.wc_outlined,
                    ),
                  ),
                ],
              ),
            ]),

            // ── Present Address ──────────────────────────────────────────
            const SizedBox(height: 16),
            _formCard([
              // Header row
              Row(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: primary.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(Icons.home_outlined, color: primary, size: 18),
                  ),
                  const SizedBox(width: 10),
                  _sectionLabel('Present Address'),
                ],
              ),
              const SizedBox(height: 14),
              // Individual address fields — pre-filled from scan, fully editable
              Row(
                children: [
                  Expanded(child: _inputField('House/Unit No.', _houseNoCtrl)),
                  const SizedBox(width: 12),
                  Expanded(child: _inputField('Street', _streetCtrl)),
                ],
              ),
              const SizedBox(height: 12),
              _inputField(
                'Barangay',
                _barangayCtrl,
                icon: Icons.location_on_outlined,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _inputField('City / Municipality', _cityCtrl),
                  ),
                  const SizedBox(width: 12),
                  Expanded(child: _inputField('Province', _provinceCtrl)),
                ],
              ),
              const SizedBox(height: 12),
              _inputField(
                'Postal Code',
                _postalCtrl,
                keyboard: TextInputType.number,
              ),

              // ── Permanent Address ──────────────────────────────────────
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(
                          color: primary.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Icon(
                          Icons.house_outlined,
                          color: primary,
                          size: 18,
                        ),
                      ),
                      const SizedBox(width: 10),
                      _sectionLabel('Permanent Address'),
                    ],
                  ),
                  Row(
                    children: [
                      Text(
                        'Same as\nPresent',
                        style: TextStyle(
                          fontSize: 10,
                          color: AppColors.textMuted,
                        ),
                        textAlign: TextAlign.right,
                      ),
                      const SizedBox(width: 4),
                      Switch.adaptive(
                        value: _sameAsPermanent,
                        onChanged: (v) => setState(() => _sameAsPermanent = v),
                        activeColor: primary,
                      ),
                    ],
                  ),
                ],
              ),
              if (!_sameAsPermanent) ...[
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _inputField('House/Unit No.', _permHouseCtrl),
                    ),
                    const SizedBox(width: 12),
                    Expanded(child: _inputField('Street', _permStreetCtrl)),
                  ],
                ),
                const SizedBox(height: 12),
                _inputField(
                  'Barangay',
                  _permBarangayCtrl,
                  icon: Icons.location_on_outlined,
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _inputField('City / Municipality', _permCityCtrl),
                    ),
                    const SizedBox(width: 12),
                    Expanded(child: _inputField('Province', _permProvinceCtrl)),
                  ],
                ),
                const SizedBox(height: 12),
                _inputField(
                  'Postal Code',
                  _permPostalCtrl,
                  keyboard: TextInputType.number,
                ),
              ],
            ]),
          ],

          // ④ Contact
          const SizedBox(height: 20),
          _formCard([
            _sectionLabel('Contact Details'),
            const SizedBox(height: 14),
            _inputField(
              'Mobile Number *',
              _phoneCtrl,
              icon: Icons.phone_outlined,
              keyboard: TextInputType.phone,
            ),
          ]),

          // ⑤ Employment
          const SizedBox(height: 16),
          _formCard([
            _sectionLabel('Employment & Income'),
            const SizedBox(height: 14),
            _dropdownField(
              'Civil Status',
              _civilStatus,
              ['Single', 'Married', 'Widowed', 'Divorced', 'Separated'],
              (v) => setState(() => _civilStatus = v!),
              icon: Icons.people_outline_rounded,
            ),
            const SizedBox(height: 12),
            _dropdownField(
              'Employment Status',
              _employmentStatus,
              _allowedEmploymentStatuses.isNotEmpty
                  ? _allowedEmploymentStatuses
                  : ['Employed'],
              (v) {
                setState(() {
                  _employmentStatus = v!;
                  final s = _employmentStatus?.toLowerCase() ?? '';

                  // Rule 1: Hide all 3 for non-employed
                  if (['retired', 'student', 'unemployed'].contains(s)) {
                    _occupationCtrl.clear();
                    _employerCtrl.clear();
                    _employerContactCtrl.clear();
                  }
                  // Rule 2: Hide employer for independent/casual
                  else if (['freelancer', 'self_employed'].contains(s)) {
                    _employerCtrl.clear();
                    _employerContactCtrl.clear();
                  }
                });
              },
              icon: Icons.work_outline_rounded,
            ),

            if (![
              'retired',
              'student',
              'unemployed',
            ].contains(_employmentStatus?.toLowerCase() ?? '')) ...[
              const SizedBox(height: 12),
              _inputField(
                'Occupation / Job Title',
                _occupationCtrl,
                icon: Icons.badge_outlined,
              ),
            ],

            if (![
              'freelancer',
              'self_employed',
              'retired',
              'student',
              'unemployed',
            ].contains(_employmentStatus?.toLowerCase() ?? '')) ...[
              const SizedBox(height: 12),
              _inputField(
                'Employer / Business Name',
                _employerCtrl,
                icon: Icons.business_outlined,
              ),
              const SizedBox(height: 12),
              _inputField(
                'Employer Contact Number',
                _employerContactCtrl,
                icon: Icons.phone_outlined,
                keyboard: TextInputType.phone,
              ),
            ],

            const SizedBox(height: 12),
            _inputField(
              'Monthly Income (₱) *',
              _monthlyIncomeCtrl,
              icon: Icons.payments_outlined,
              keyboard: TextInputType.number,
            ),
          ]),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  // ══════════════════════════════════════════════════════════════════════
  //  STEP 1 – Co-maker
  // ══════════════════════════════════════════════════════════════════════
  Widget _buildStep1(Color primary) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _formCard([
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _sectionLabel('Co-maker'),
                      const SizedBox(height: 4),
                      Text(
                        'Add a co-maker to support your application',
                        style: TextStyle(
                          fontSize: 11,
                          color: AppColors.textMuted,
                        ),
                      ),
                    ],
                  ),
                ),
                Switch.adaptive(
                  value: _hasComaker,
                  onChanged: (v) => setState(() => _hasComaker = v),
                  activeColor: primary,
                ),
              ],
            ),
          ]),
          if (_hasComaker) ...[
            const SizedBox(height: 16),
            _formCard([
              _sectionLabel('Co-maker Information'),
              const SizedBox(height: 14),
              _inputField(
                'Full Name *',
                _comakerNameCtrl,
                icon: Icons.person_outline_rounded,
              ),
              const SizedBox(height: 12),
              _inputField(
                'Relationship to Applicant *',
                _comakerRelCtrl,
                icon: Icons.family_restroom_outlined,
              ),
              const SizedBox(height: 12),
              _inputField(
                'Contact Number *',
                _comakerContactCtrl,
                icon: Icons.phone_outlined,
                keyboard: TextInputType.phone,
              ),
              const SizedBox(height: 12),
              _inputField(
                'Monthly Income (₱)',
                _comakerIncomeCtrl,
                icon: Icons.payments_outlined,
                keyboard: TextInputType.number,
              ),
              const SizedBox(height: 12),
              _inputField(
                'Complete Address',
                _comakerAddressCtrl,
                icon: Icons.location_on_outlined,
                maxLines: 2,
              ),
            ]),
          ],
        ],
      ),
    );
  }

  // ══════════════════════════════════════════════════════════════════════
  //  STEP 2 – Documents
  // ══════════════════════════════════════════════════════════════════════
  Widget _buildStep2(Color primary) {
    final uploaded = _docTypes
        .where(
          (d) =>
              _selectedDocs[int.tryParse(d['document_type_id'].toString())] !=
              null,
        )
        .length;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _infoChip(
            primary,
            Icons.folder_copy_outlined,
            '$uploaded / ${_docTypes.length} Documents Uploaded',
            'Please provide proof of income, billing, and legitimacy.',
          ),
          const SizedBox(height: 16),
          if (_isLoadingDocs)
            Center(child: CircularProgressIndicator(color: primary))
          else if (_docTypes.isEmpty)
            Text(
              'No additional documents required.',
              style: TextStyle(color: AppColors.textMuted),
            )
          else
            ..._docTypes.map((d) => _docPicker(d, primary)),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  // ══════════════════════════════════════════════════════════════════════
  //  STEP 3 – Review & Submit
  // ══════════════════════════════════════════════════════════════════════
  Widget _buildStep3(Color primary) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _infoChip(
            primary,
            Icons.assignment_turned_in_rounded,
            'Final Review',
            'Please verify your information before submitting.',
          ),
          const SizedBox(height: 16),

          _reviewCard('Identity', Icons.badge_outlined, primary, [
            _reviewRow(
              'ID Type',
              _activeIdType.isNotEmpty ? _activeIdType['l'] as String : '—',
            ),
            _reviewRow(
              'ID Photo',
              _idPath != null ? 'Uploaded ✓' : 'Not uploaded',
            ),
          ]),
          const SizedBox(height: 12),
          _reviewCard('Personal', Icons.person_outline_rounded, primary, [
            _reviewRow(
              'Full Name',
              _fullNameCtrl.text.isNotEmpty ? _fullNameCtrl.text : '—',
            ),
            _reviewRow(
              'Date of Birth',
              _dobCtrl.text.isNotEmpty ? _dobCtrl.text : '—',
            ),
            _reviewRow('Gender', _gender),
            _reviewRow('Civil Status', _civilStatus),
            _reviewRow('Employment', _employmentStatus),
            if (_monthlyIncomeCtrl.text.isNotEmpty)
              _reviewRow('Monthly Income', '₱${_monthlyIncomeCtrl.text}'),
          ]),
          const SizedBox(height: 12),
          _reviewCard('Contact & Address', Icons.home_outlined, primary, [
            _reviewRow(
              'Mobile',
              _phoneCtrl.text.isNotEmpty ? _phoneCtrl.text : '—',
            ),
            _reviewRow(
              'Present Address',
              _effectivePresentAddress.isNotEmpty
                  ? _effectivePresentAddress
                  : '—',
            ),
          ]),
          if (_hasComaker) ...[
            const SizedBox(height: 12),
            _reviewCard('Co-maker', Icons.people_outline_rounded, primary, [
              _reviewRow('Name', _comakerNameCtrl.text),
              _reviewRow('Relationship', _comakerRelCtrl.text),
              _reviewRow('Contact', _comakerContactCtrl.text),
            ]),
          ],
          const SizedBox(height: 20),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFFF9FAFB),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.verified_user_outlined,
                  color: Color(0xFF10B981),
                  size: 20,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'By submitting, you confirm that all information provided is accurate and truthful.',
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.textMain,
                      height: 1.5,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  // ── Shared widgets ─────────────────────────────────────────────────────
  Widget _infoChip(Color primary, IconData icon, String title, String sub) =>
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.black.withOpacity(0.04),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: Colors.black.withOpacity(0.1)),
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.06),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: Colors.black, size: 24),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: Colors.black,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    sub,
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted),
                  ),
                ],
              ),
            ),
          ],
        ),
      );

  Widget _sectionLabel(String t) => Padding(
    padding: const EdgeInsets.only(bottom: 2),
    child: Text(
      t,
      style: const TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w900,
        color: Color(0xFF111827),
        letterSpacing: -0.3,
      ),
    ),
  );

  Widget _formCard(List<Widget> children) => Container(
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(24),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: Colors.black, width: 1.2),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: children,
    ),
  );

  InputDecoration _dropDecor(Color primary) => InputDecoration(
    filled: true,
    fillColor: AppColors.bg,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: BorderSide(
        color: AppColors.border.withOpacity(0.5),
        width: 1,
      ),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: BorderSide(color: primary, width: 1.5),
    ),
  );

  Widget _inputField(
    String label,
    TextEditingController ctrl, {
    IconData? icon,
    TextInputType keyboard = TextInputType.text,
    int maxLines = 1,
  }) => TextFormField(
    controller: ctrl,
    keyboardType: keyboard,
    maxLines: maxLines,
    style: TextStyle(
      fontSize: 14,
      color: AppColors.textMain,
      fontWeight: FontWeight.w700,
    ),
    decoration: AppPremium.fieldDecoration(label: label, icon: icon),
  );

  Widget _dropdownField(
    String label,
    String value,
    List<String> items,
    void Function(String?) onChanged, {
    IconData? icon,
  }) => DropdownButtonFormField<String>(
    value: value,
    onChanged: onChanged,
    style: TextStyle(
      fontSize: 14,
      color: AppColors.textMain,
      fontWeight: FontWeight.w700,
    ),
    decoration: AppPremium.fieldDecoration(label: label, icon: icon),
    items: items
        .map(
          (e) => DropdownMenuItem(
            value: e,
            child: Text(AppFormat.normalizeLabel(e)),
          ),
        )
        .toList(),
  );

  Widget _dateTap(
    TextEditingController ctrl,
    String label,
    IconData icon,
    Color primary, {
    required DateTime first,
    required DateTime last,
    required DateTime initial,
  }) => GestureDetector(
    onTap: () async {
      final p = await showDatePicker(
        context: context,
        initialDate: initial,
        firstDate: first,
        lastDate: last,
        builder: (c, child) => Theme(
          data: Theme.of(
            c,
          ).copyWith(colorScheme: ColorScheme.light(primary: primary)),
          child: child!,
        ),
      );
      if (p != null) setState(() => ctrl.text = p.toString().split(' ')[0]);
    },
    child: AbsorbPointer(child: _inputField(label, ctrl, icon: icon)),
  );

  Widget _docPicker(dynamic d, Color primary) {
    final id = int.parse(d['document_type_id'].toString());
    final isSel = _selectedDocs[id] != null;
    return GestureDetector(
      onTap: () => _pickAndUploadDocument(id),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: isSel ? primary.withOpacity(0.05) : AppColors.card,
          border: Border.all(color: isSel ? primary : AppColors.border),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: isSel ? primary : AppColors.bg,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                isSel ? Icons.check_circle_rounded : Icons.camera_alt_outlined,
                color: isSel ? Colors.white : AppColors.textMuted,
                size: 20,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    d['document_name'],
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF111827),
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    isSel ? 'File selected ✓' : 'Tap to upload',
                    style: TextStyle(
                      color: isSel
                          ? primary.withOpacity(0.8)
                          : AppColors.textMuted,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _reviewCard(
    String title,
    IconData icon,
    Color primary,
    List<Widget> rows,
  ) => Container(
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: Colors.black, width: 1.2),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Row(
            children: [
              Icon(icon, size: 18, color: primary),
              const SizedBox(width: 10),
              Text(
                title,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF111827),
                ),
              ),
            ],
          ),
        ),
        const Divider(height: 1),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
          child: Column(children: rows),
        ),
      ],
    ),
  );

  Widget _reviewRow(String label, String value) => Padding(
    padding: const EdgeInsets.only(top: 12),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 13,
            color: AppColors.textMuted,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: TextStyle(
              fontSize: 13,
              color: AppColors.textMain,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );

  Widget _reviewHighlight(String label, String value, Color primary) =>
      Container(
        margin: const EdgeInsets.only(top: 10),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: Colors.black.withOpacity(0.03),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Colors.black.withOpacity(0.1)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                const Icon(
                  Icons.verified_rounded,
                  size: 13,
                  color: Colors.black,
                ),
                const SizedBox(width: 5),
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 12,
                    color: Colors.black,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                value,
                textAlign: TextAlign.right,
                style: const TextStyle(
                  fontSize: 13,
                  color: Colors.black,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      );
}
