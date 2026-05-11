import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;
import 'package:mobile_scanner/mobile_scanner.dart';
import 'dart:async';


import '../main.dart';
import '../models/tenant_branding.dart';
import '../theme.dart';
import '../utils/api_config.dart';
import '../widgets/microfin_logo.dart';
import 'main_layout.dart';
import 'splash_screen.dart';

Map<String, dynamic> _decodeApiMap(String body) {
  final jsonStart = body.indexOf('{');
  final normalizedBody = jsonStart > 0 ? body.substring(jsonStart) : body;
  final decoded = jsonDecode(normalizedBody);
  if (decoded is Map<String, dynamic>) {
    return decoded;
  }
  if (decoded is Map) {
    return decoded.map((key, value) => MapEntry('$key', value));
  }
  throw const FormatException('Invalid API response');
}

Map<String, dynamic> _stringMap(dynamic value) {
  if (value is Map<String, dynamic>) {
    return value;
  }
  if (value is Map) {
    return value.map((key, value) => MapEntry('$key', value));
  }
  return <String, dynamic>{};
}

String _cleanString(dynamic value) => value?.toString().trim() ?? '';

String _buildLoginUsername(String baseUsername, String tenantSlug) {
  final cleanedBase = baseUsername.trim().replaceAll('@', '');
  final cleanedSlug = tenantSlug.trim().replaceAll('@', '');
  if (cleanedBase.isEmpty || cleanedSlug.isEmpty) {
    return '';
  }
  return '$cleanedBase@$cleanedSlug';
}

int _calculatePasswordStrength(String password) {
  int strength = 0;
  if (password.isNotEmpty) {
    if (password.length >= 8) strength = 1;
    if (password.length >= 8 &&
        RegExp(r'[A-Za-z]').hasMatch(password) &&
        RegExp(r'[0-9]').hasMatch(password)) {
      strength = 2;
    }
    if (password.length >= 10 &&
        RegExp(r'[A-Z]').hasMatch(password) &&
        RegExp(r'[0-9]').hasMatch(password) &&
        RegExp(r'[^a-zA-Z0-9]').hasMatch(password)) {
      strength = 3;
    }
    if (password.length >= 12 &&
        RegExp(r'[A-Z]').hasMatch(password) &&
        RegExp(r'[a-z]').hasMatch(password) &&
        RegExp(r'[0-9]').hasMatch(password) &&
        RegExp(r'[^a-zA-Z0-9]').hasMatch(password)) {
      strength = 4;
    }
  }
  return strength;
}

Color _passwordStrengthColor(int strength, int index) {
  if (index >= strength) {
    return const Color(0xFFE2E8F0);
  }
  if (strength == 1) {
    return AppColors.error;
  }
  if (strength == 2) {
    return Colors.orange;
  }
  if (strength == 3) {
    return Colors.amber;
  }
  return const Color(0xFF10B981);
}

String _passwordStrengthLabel(int strength) {
  switch (strength) {
    case 1:
      return 'Weak';
    case 2:
      return 'Fair';
    case 3:
      return 'Good';
    case 4:
      return 'Strong';
    default:
      return '';
  }
}

class LoginScreen extends StatefulWidget {
  final bool openRegistrationOnLoad;

  const LoginScreen({super.key, this.openRegistrationOnLoad = false});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen>
    with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _loginUsernameController = TextEditingController();
  final _passwordController = TextEditingController();
  late final AnimationController _controller;
  late final Animation<double> _fadeAnimation;
  late final Animation<Offset> _slideAnimation;

  bool _isLoading = false;
  bool _obscurePassword = true;
  
  Timer? _discoveryTimer;

  String? _lastDiscoveredSlug;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 650),
    )..forward();
    _fadeAnimation = CurvedAnimation(
      parent: _controller,
      curve: Curves.easeOutCubic,
    );
    _slideAnimation = Tween<Offset>(
      begin: const Offset(0, 0.06),
      end: Offset.zero,
    ).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic),
    );

    if (widget.openRegistrationOnLoad) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          _showRegistrationModal();
        }
      });
    }
  }

  void _handleUsernameDiscovery(String value) {
    if (_discoveryTimer?.isActive ?? false) _discoveryTimer!.cancel();
    _discoveryTimer = Timer(const Duration(milliseconds: 250), () {
      if (!mounted) return;
      
      final text = value.trim();
      if (!text.contains('@')) {
        _revertToDefaultTenant();
        return;
      }

      final parts = text.split('@');
      if (parts.length < 2 || parts.last.length < 2) {
        _revertToDefaultTenant();
        return;
      }

      final slug = parts.last.toLowerCase();
      if (slug == _lastDiscoveredSlug) return;
      
      _lastDiscoveredSlug = slug;
      _discoverTenant(slug);
    });
  }

  void _revertToDefaultTenant() {
    if (activeTenant.value.id != TenantBranding.defaultTenant.id) {
      activeTenant.value = TenantBranding.defaultTenant;
      _lastDiscoveredSlug = null;
    }
  }

  Future<void> _discoverTenant(String slug) async {
    try {
      final response = await http.post(
        Uri.parse(ApiConfig.getUrl('api_resolve_tenant_reference.php')),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'referral_code': slug,
          'tenant_slug': slug,
        }),
      );

      final data = _decodeApiMap(response.body);
      if (data['success'] == true && mounted) {
        final tenantPayload = _stringMap(data['tenant']);
        final tenantBranding = TenantBranding.fromJson(tenantPayload);
        
        // Update the global theme context
        if (activeTenant.value.id != tenantBranding.id) {
          activeTenant.value = tenantBranding;
        }
      }
    } catch (_) {
      // Fail silently for discovery
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    _loginUsernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() => _isLoading = true);

    try {
      final response = await http
          .post(
            Uri.parse(ApiConfig.getUrl('api_login.php')),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'login_username': _loginUsernameController.text.trim(),
              'password': _passwordController.text,
            }),
          )
          .timeout(const Duration(seconds: 20));

      final data = _decodeApiMap(response.body);

      if (data['success'] == true) {
        final tenantPayload = _stringMap(data['tenant']);
        final tenantBranding = TenantBranding.fromJson(tenantPayload);
        activeTenant.value = tenantBranding;

        final loginUsername = _cleanString(data['login_username']);
        final baseUsername = loginUsername.contains('@')
            ? loginUsername.split('@').first
            : _loginUsernameController.text.trim().split('@').first;

        currentUser.value = {
          'user_id': data['user_id'],
          'client_id': data['client_id'] ?? 0,
          'tenant_id': tenantBranding.id,
          'username': baseUsername,
          'login_username': loginUsername,
          'first_name': data['first_name'] ?? '',
          'last_name': data['last_name'] ?? '',
          'email': data['email'] ?? '',
          'verification_status': data['verification_status'] ?? 'Unverified',
          'credit_limit': data['credit_limit'] ?? 0,
        };

        if (!mounted) {
          return;
        }

        Navigator.of(context).pushReplacement(
          PageRouteBuilder(
            transitionDuration: const Duration(milliseconds: 350),
            pageBuilder: (_, __, ___) => const MainLayout(),
            transitionsBuilder: (_, animation, __, child) {
              return FadeTransition(opacity: animation, child: child);
            },
          ),
        );
        return;
      }

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_cleanString(data['message']).isEmpty
              ? 'Login failed.'
              : _cleanString(data['message'])),
          backgroundColor: AppColors.error,
        ),
      );
    } catch (e) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Connection Error: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _showRegistrationModal() async {
    final result = await showModalBottomSheet<_RegistrationCompletion>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      isDismissible: false,
      enableDrag: false,
      backgroundColor: Colors.transparent,
      builder: (_) => const _RegistrationModal(),
    );

    if (result == null || !mounted) {
      return;
    }

    activeTenant.value = result.tenant;
    setState(() {
      _loginUsernameController.text = result.loginUsername;
      _passwordController.clear();
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Account created. Sign in with ${result.loginUsername}.',
        ),
      ),
    );
  }

  Future<void> _showForgotPasswordModal() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _ForgotPasswordModal(),
    );
  }

  void _goBack() {
    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 300),
        pageBuilder: (_, __, ___) => const SplashScreen(),
        transitionsBuilder: (_, animation, __, child) {
          return FadeTransition(opacity: animation, child: child);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<TenantBranding>(
      valueListenable: activeTenant,
      builder: (context, tenant, _) {
        final primary = tenant.themePrimaryColor;
        final secondary = tenant.themeSecondaryColor;
        final size = MediaQuery.of(context).size;
        final headerHeight = size.height * 0.38;

        return Scaffold(
          backgroundColor: Colors.white,
          body: Stack(
            children: [
              Positioned(
                top: 0,
                left: 0,
                right: 0,
                height: headerHeight,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Color.lerp(primary, secondary, 0.15)!, primary],
                    ),
                  ),
                ),
              ),
              SafeArea(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    FadeTransition(
                      opacity: _fadeAnimation,
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
                        child: GestureDetector(
                          onTap: _goBack,
                          child: Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.18),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: Colors.white.withOpacity(0.35),
                                width: 1,
                              ),
                            ),
                            child: const Icon(
                              Icons.arrow_back_rounded,
                              color: Colors.white,
                              size: 22,
                            ),
                          ),
                        ),
                      ),
                    ),
                    FadeTransition(
                      opacity: _fadeAnimation,
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(24, 28, 24, 0),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (tenant.logoPath.isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 16),
                                child: Container(
                                  width: 64,
                                  height: 64,
                                  decoration: BoxDecoration(
                                    color: Colors.white.withOpacity(0.12),
                                    borderRadius: BorderRadius.circular(16),
                                    border: Border.all(
                                      color: Colors.white.withOpacity(0.24),
                                    ),
                                  ),
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(14),
                                    child: Image.network(
                                      tenant.logoPath.startsWith('http')
                                          ? tenant.logoPath
                                          : '${ApiConfig.appBaseUrl}/admin-draft/microfin_web/uploads/tenant_logos/${tenant.logoPath}',
                                      fit: BoxFit.contain,
                                      errorBuilder: (_, __, ___) => const Icon(
                                        Icons.business_rounded,
                                        color: Colors.white,
                                        size: 32,
                                      ),
                                    ),
                                  ),
                                ),
                              )
                            else if (tenant.id == TenantBranding.defaultTenant.id)
                              const Padding(
                                padding: EdgeInsets.only(bottom: 16),
                                child: MicroFinLogo(size: 48, elevated: false),
                              ),
                            Text(
                              'Sign in',
                              style: GoogleFonts.outfit(
                                fontSize: 32,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                                letterSpacing: -0.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    Expanded(
                      child: SlideTransition(
                        position: _slideAnimation,
                        child: FadeTransition(
                          opacity: _fadeAnimation,
                          child: Container(
                            width: double.infinity,
                            margin: EdgeInsets.only(top: headerHeight * 0.14),
                            decoration: const BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.vertical(
                                top: Radius.circular(32),
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: Color(0x1A000000),
                                  blurRadius: 30,
                                  offset: Offset(0, -6),
                                ),
                              ],
                            ),
                            child: SingleChildScrollView(
                              physics: const BouncingScrollPhysics(),
                              padding: const EdgeInsets.fromLTRB(28, 36, 28, 32),
                              child: Form(
                                key: _formKey,
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Center(
                                      child: Container(
                                        width: 44,
                                        height: 4,
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFE2E8F0),
                                          borderRadius: BorderRadius.circular(4),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(height: 28),
                                    Text(
                                      'Welcome Back',
                                      style: GoogleFonts.outfit(
                                        fontSize: 26,
                                        fontWeight: FontWeight.w800,
                                        color: const Color(0xFF1A1A2E),
                                        letterSpacing: -0.4,
                                      ),
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      tenant.id ==
                                              TenantBranding.defaultTenant.id
                                          ? 'Sign in with your username to continue.'
                                          : 'Use your username to continue in ${tenant.appName}.',
                                      style: GoogleFonts.inter(
                                        fontSize: 14,
                                        color: AppColors.textMuted,
                                      ),
                                    ),
                                    const SizedBox(height: 20),
                                    Container(
                                      padding: const EdgeInsets.all(14),
                                      decoration: BoxDecoration(
                                        color: primary.withOpacity(0.08),
                                        borderRadius: BorderRadius.circular(16),
                                        border: Border.all(
                                          color: primary.withOpacity(0.16),
                                        ),
                                      ),
                                      child: Row(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Icon(
                                            Icons.badge_outlined,
                                            color: primary,
                                            size: 20,
                                          ),
                                          const SizedBox(width: 10),
                                          Expanded(
                                            child: Text(
                                              'Use the exact format username@institution when signing in or recovering your password.',
                                              style: GoogleFonts.inter(
                                                fontSize: 12.5,
                                                color: AppColors.textMain,
                                                height: 1.45,
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(height: 28),
                                    Text(
                                      'Username',
                                      style: GoogleFonts.inter(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                        color: const Color(0xFF6B7280),
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    _LegacyLoginField(
                                      hint: 'username@institution',
                                      controller: _loginUsernameController,
                                      primary: primary,
                                      onChanged: _handleUsernameDiscovery,
                                      keyboardType: TextInputType.emailAddress,
                                      validator: (value) {
                                        final text = value?.trim() ?? '';
                                        if (text.isEmpty) {
                                          return 'Enter your username.';
                                        }
                                        if (!text.contains('@')) {
                                          return 'Use the format username@institution.';
                                        }
                                        return null;
                                      },
                                    ),
                                    const SizedBox(height: 20),
                                    Text(
                                      'Password',
                                      style: GoogleFonts.inter(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                        color: const Color(0xFF6B7280),
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    _LegacyLoginField(
                                      hint: 'Enter your password',
                                      controller: _passwordController,
                                      primary: primary,
                                      obscureText: _obscurePassword,
                                      suffixIcon: GestureDetector(
                                        onTap: () {
                                          setState(() {
                                            _obscurePassword =
                                                !_obscurePassword;
                                          });
                                        },
                                        child: Icon(
                                          _obscurePassword
                                              ? Icons.visibility_off_rounded
                                              : Icons.visibility_rounded,
                                          color: primary,
                                          size: 20,
                                        ),
                                      ),
                                      validator: (value) {
                                        if ((value ?? '').isEmpty) {
                                          return 'Enter your password.';
                                        }
                                        return null;
                                      },
                                    ),
                                    Align(
                                      alignment: Alignment.centerLeft,
                                      child: TextButton(
                                        onPressed: _showForgotPasswordModal,
                                        style: TextButton.styleFrom(
                                          padding: const EdgeInsets.symmetric(
                                            vertical: 10,
                                          ),
                                          minimumSize: Size.zero,
                                          tapTargetSize:
                                              MaterialTapTargetSize
                                                  .shrinkWrap,
                                        ),
                                        child: Text(
                                          'Forgot Password or Username?',
                                          style: GoogleFonts.inter(
                                            color: primary,
                                            fontSize: 13,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(height: 20),
                                    _PremiumButton(
                                      label: 'Sign In',
                                      isLoading: _isLoading,
                                      primary: primary,
                                      secondary: secondary,
                                      onPressed: _handleLogin,
                                    ),
                                    const SizedBox(height: 32),
                                    Center(
                                      child: Text.rich(
                                        TextSpan(
                                          text: "Don't have an account? ",
                                          style: GoogleFonts.inter(
                                            fontSize: 14,
                                            color: AppColors.textMuted,
                                          ),
                                          children: [
                                            WidgetSpan(
                                              alignment:
                                                  PlaceholderAlignment.baseline,
                                              baseline: TextBaseline.alphabetic,
                                              child: GestureDetector(
                                                onTap: _showRegistrationModal,
                                                child: Text(
                                                  'Sign up',
                                                  style: GoogleFonts.inter(
                                                    fontSize: 14,
                                                    color: primary,
                                                    fontWeight: FontWeight.w700,
                                                  ),
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _RegistrationModal extends StatefulWidget {
  const _RegistrationModal();

  @override
  State<_RegistrationModal> createState() => _RegistrationModalState();
}

class _RegistrationModalState extends State<_RegistrationModal> {
  final _referralController = TextEditingController();
  final _firstNameController = TextEditingController();
  final _middleNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _suffixController = TextEditingController();
  final _emailController = TextEditingController();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final _otpController = TextEditingController();

  _ResolvedTenant? _tenant;
  bool _isLoading = false;
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;
  bool _ignoreReferralListener = false;
  int _step = 1;
  int _passwordStrength = 0;
  String _finalLoginUsername = '';
  String _pendingToken = '';
  String? _errorMessage;
  String? _infoMessage;

  @override
  void initState() {
    super.initState();
    _referralController.addListener(_handleReferralChange);
    _passwordController.addListener(_handlePasswordStrengthChange);
    _otpController.addListener(_handleOtpInputChange);
    _usernameController.addListener(() {
      if (mounted) {
        setState(() {});
      }
    });
  }

  @override
  void dispose() {
    _referralController.dispose();
    _firstNameController.dispose();
    _middleNameController.dispose();
    _lastNameController.dispose();
    _suffixController.dispose();
    _emailController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  bool get _unlocked => _tenant != null && _step == 1;

  void _handlePasswordStrengthChange() {
    final strength = _calculatePasswordStrength(_passwordController.text);
    if (_passwordStrength != strength && mounted) {
      setState(() => _passwordStrength = strength);
    }
  }

  void _handleOtpInputChange() {
    if (_step != 2 || _isLoading) {
      return;
    }

    final otp = _otpController.text.trim();
    if (otp.length == 6) {
      _verifyRegistrationCode();
    } else if (_errorMessage != null && otp.isNotEmpty && mounted) {
      setState(() => _errorMessage = null);
    }
  }

  void _handleReferralChange() {
    if (_ignoreReferralListener || _tenant == null || _step != 1) {
      return;
    }

    if (_referralController.text.trim().toLowerCase() !=
        _tenant!.slug.toLowerCase()) {
      setState(() {
        _tenant = null;
        _errorMessage = null;
        _infoMessage =
            'Tenant reference changed. Validate it again to unlock the form.';
      });
    }
  }

  Future<void> _resolveTenant(Map<String, dynamic> payload) async {
    FocusScope.of(context).unfocus();
    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _infoMessage = null;
    });

    try {
      final response = await http
          .post(
            Uri.parse(ApiConfig.getUrl('api_resolve_tenant_reference.php')),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode(payload),
          )
          .timeout(const Duration(seconds: 20));

      final data = _decodeApiMap(response.body);
      if (data['success'] == true) {
        final resolvedTenant = _ResolvedTenant.fromResponse(data);
        _ignoreReferralListener = true;
        _referralController.text = resolvedTenant.slug;
        _ignoreReferralListener = false;
        setState(() {
          _tenant = resolvedTenant;
          _infoMessage =
              'Tenant verified. Registration is now unlocked for ${resolvedTenant.name}.';
        });
      } else {
        setState(() {
          _tenant = null;
          _errorMessage = _cleanString(data['message']).isEmpty
              ? 'Unable to validate that tenant reference.'
              : _cleanString(data['message']);
        });
      }
    } catch (_) {
      setState(() {
        _tenant = null;
        _errorMessage = 'Failed to resolve the tenant reference.';
      });
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _resolveReferral() async {
    final code = _referralController.text.trim();
    if (code.isEmpty) {
      setState(() {
        _errorMessage = 'Enter the referral code first.';
        _infoMessage = null;
      });
      return;
    }
    await _resolveTenant({'referral_code': code});
  }

  Future<void> _scanQr() async {
    final payload = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _QrScannerSheet(),
    );
    if (!mounted || payload == null || payload.trim().isEmpty) {
      return;
    }
    await _resolveTenant({'qr_payload': payload.trim()});
  }

  Future<void> _uploadQr() async {
    try {
      final file = await FilePicker.platform.pickFiles(
        type: FileType.image,
        allowMultiple: false,
      );
      final path = file?.files.single.path;
      if (path == null || path.isEmpty) {
        return;
      }

      final analyzer = MobileScannerController(autoStart: false);
      final capture = await analyzer.analyzeImage(
        path,
        formats: const [BarcodeFormat.qrCode],
      );
      analyzer.dispose();

      final payload = capture?.barcodes
              .map((barcode) => barcode.rawValue?.trim() ?? '')
              .firstWhere((value) => value.isNotEmpty, orElse: () => '') ??
          '';

      if (payload.isEmpty) {
        setState(() {
          _errorMessage =
              'No valid QR code was found in that image. Try another image or use the referral code.';
          _infoMessage = null;
        });
        return;
      }

      await _resolveTenant({'qr_payload': payload});
    } catch (_) {
      setState(() {
        _errorMessage = 'Unable to read the QR image.';
        _infoMessage = null;
      });
    }
  }

  Future<void> _register() async {
    if (_tenant == null) {
      setState(() {
        _errorMessage = 'Validate a tenant reference before registering.';
        _infoMessage = null;
      });
      return;
    }

    final firstName = _firstNameController.text.trim();
    final lastName = _lastNameController.text.trim();
    final email = _emailController.text.trim();
    final usernameBase = _usernameController.text.trim();
    final password = _passwordController.text;
    final confirmPassword = _confirmPasswordController.text;

    if (firstName.isEmpty ||
        lastName.isEmpty ||
        email.isEmpty ||
        usernameBase.isEmpty ||
        password.isEmpty) {
      setState(() {
        _errorMessage = 'Complete all required registration fields.';
        _infoMessage = null;
      });
      return;
    }

    if (!email.contains('@')) {
      setState(() {
        _errorMessage = 'Enter a valid real email address.';
        _infoMessage = null;
      });
      return;
    }

    if (usernameBase.contains('@')) {
      setState(() {
        _errorMessage =
            'Username cannot include @. The tenant suffix is fixed.';
        _infoMessage = null;
      });
      return;
    }

    if (password.length < 8) {
      setState(() {
        _errorMessage = 'Use a password with at least 8 characters.';
        _infoMessage = null;
      });
      return;
    }

    if (password != confirmPassword) {
      setState(() {
        _errorMessage = 'Passwords do not match.';
        _infoMessage = null;
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _infoMessage = null;
    });

    try {
      final response = await http
          .post(
            Uri.parse(ApiConfig.getUrl('api_register.php')),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'tenant_context_token': _tenant!.token,
              'base_username': usernameBase,
              'email': email,
              'password': password,
              'first_name': firstName,
              'middle_name': _middleNameController.text.trim(),
              'last_name': lastName,
              'suffix': _suffixController.text.trim(),
            }),
          )
          .timeout(const Duration(seconds: 20));

      final data = _decodeApiMap(response.body);
      if (data['success'] == true) {
        final returnedToken = _cleanString(data['tenant_context_token']);
        final returnedPendingToken = _cleanString(data['pending_token']);
        setState(() {
          _step = 2;
          _otpController.clear();
          _finalLoginUsername = _cleanString(data['login_username']);
          _pendingToken = returnedPendingToken;
          _tenant = _ResolvedTenant(
            tenant: _tenant!.tenant,
            token: returnedToken.isEmpty ? _tenant!.token : returnedToken,
            name: _tenant!.name,
            slug: _tenant!.slug,
          );
          _infoMessage = _cleanString(data['message']).isEmpty
              ? 'Verification code sent. Enter the 6-digit code below to finish creating your account.'
              : _cleanString(data['message']);
        });
      } else {
        setState(() {
          _errorMessage = _cleanString(data['message']).isEmpty
              ? 'Registration failed.'
              : _cleanString(data['message']);
        });
      }
    } catch (_) {
      setState(() {
        _errorMessage = 'Failed to submit registration.';
      });
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _verifyRegistrationCode() async {
    if (_tenant == null) {
      setState(() {
        _step = 1;
        _errorMessage = 'Tenant context expired. Please restart registration.';
        _infoMessage = null;
      });
      return;
    }

    final otp = _otpController.text.trim();
    if (otp.length != 6) {
      setState(() {
        _errorMessage = 'Enter the complete 6-digit verification code.';
        _infoMessage = null;
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _infoMessage = null;
    });

    try {
      final response = await http
          .post(
            Uri.parse(ApiConfig.getUrl('api_verify_registration_otp.php')),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'email': _emailController.text.trim(),
              'otp': otp,
              'login_username': _finalLoginUsername,
              'tenant_context_token': _tenant!.token,
              if (_pendingToken.isNotEmpty) 'pending_token': _pendingToken,
            }),
          )
          .timeout(const Duration(seconds: 20));

      final data = _decodeApiMap(response.body);
      if (data['success'] == true) {
        setState(() {
          _step = 3;
          _finalLoginUsername = _cleanString(data['login_username']).isEmpty
              ? _finalLoginUsername
              : _cleanString(data['login_username']);
          _infoMessage = _cleanString(data['message']).isEmpty
              ? 'Email verified successfully.'
              : _cleanString(data['message']);
        });
      } else {
        setState(() {
          _errorMessage = _cleanString(data['message']).isEmpty
              ? 'Unable to verify the code.'
              : _cleanString(data['message']);
        });
      }
    } catch (_) {
      setState(() {
        _errorMessage = 'Failed to verify the registration code.';
      });
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  String get _loginPreview => _tenant == null
      ? ''
      : _buildLoginUsername(_usernameController.text.trim(), _tenant!.slug);

  Future<bool> _confirmCloseIfVerificationPending(BuildContext context) async {
    if (_step != 2) {
      return true;
    }

    final shouldLeave = await showDialog<bool>(
          context: context,
          builder: (dialogContext) {
            return AlertDialog(
              title: const Text('Leave verification?'),
              content: Text(
                'Your verification code is still pending for $_finalLoginUsername. You can stay here and enter the code, or leave for now and come back later.',
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(dialogContext).pop(false),
                  child: const Text('Stay'),
                ),
                FilledButton(
                  onPressed: () => Navigator.of(dialogContext).pop(true),
                  child: const Text('Leave'),
                ),
              ],
            );
          },
        ) ??
        false;

    return shouldLeave;
  }

  @override
  Widget build(BuildContext context) {
    final tenantBranding = _tenant?.tenant ?? activeTenant.value;
    final primary = tenantBranding.themePrimaryColor;
    final secondary = tenantBranding.themeSecondaryColor;
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return _buildLegacyRegistrationSheet(
      context,
      primary: primary,
      secondary: secondary,
      bottomInset: bottomInset,
    );
  }

  Widget _buildLegacyRegistrationSheet(
    BuildContext context, {
    required Color primary,
    required Color secondary,
    required double bottomInset,
  }) {
    return WillPopScope(
      onWillPop: () => _confirmCloseIfVerificationPending(context),
      child: Container(
        margin: EdgeInsets.only(top: MediaQuery.of(context).padding.top + 20),
        padding: EdgeInsets.only(bottom: bottomInset),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        ),
        child: Material(
          color: Colors.transparent,
          child: CustomScrollView(
            shrinkWrap: true,
            physics: const BouncingScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(24, 20, 24, 0),
                  child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 44,
                        height: 4,
                        decoration: BoxDecoration(
                          color: const Color(0xFFE2E8F0),
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    Row(
                      children: List.generate(
                        3,
                        (index) => Container(
                          width: index == (_step - 1) ? 24 : 8,
                          height: 8,
                          margin: const EdgeInsets.only(right: 6),
                          decoration: BoxDecoration(
                            color: index <= (_step - 1)
                                ? primary
                                : primary.withOpacity(0.25),
                            borderRadius: BorderRadius.circular(4),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _step == 1
                                    ? 'Create Account'
                                    : _step == 2
                                        ? 'Verify Email'
                                        : 'Account Ready',
                                style: GoogleFonts.outfit(
                                  fontSize: 26,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.textMain,
                                  letterSpacing: -0.6,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                _step == 1
                                    ? 'Validate your institution first, then complete the account form.'
                                    : _step == 2
                                        ? 'Enter the verification code sent to your real email address.'
                                        : 'Use this exact username the next time you sign in.',
                                style: GoogleFonts.inter(
                                  fontSize: 14,
                                  color: AppColors.textMuted,
                                ),
                              ),
                            ],
                          ),
                        ),
                        IconButton(
                          onPressed: () async {
                            if (await _confirmCloseIfVerificationPending(
                              context,
                            )) {
                              if (mounted) {
                                Navigator.pop(context);
                              }
                            }
                          },
                          icon: Icon(
                            Icons.close_rounded,
                            color: AppColors.textMuted,
                            size: 26,
                          ),
                          splashRadius: 24,
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                    if (_errorMessage != null)
                      _StatusBanner(message: _errorMessage!, isError: true),
                    if (_infoMessage != null)
                      _StatusBanner(message: _infoMessage!, isError: false),
                    if (_step == 1 || _step == 2) ...[
                      _TenantReferenceCard(
                        referralController: _referralController,
                        isLoading: _isLoading,
                        tenant: _tenant,
                        primary: primary,
                        onResolveReferral: _resolveReferral,
                        onScanQr: _scanQr,
                        onUploadQr: _uploadQr,
                        onChangeTenant: _step == 1
                            ? () {
                                setState(() {
                                  _tenant = null;
                                  _infoMessage = null;
                                  _errorMessage = null;
                                });
                              }
                            : null,
                      ),
                      const SizedBox(height: 20),
                      AnimatedOpacity(
                        opacity: _tenant != null ? 1 : 0.52,
                        duration: const Duration(milliseconds: 180),
                        child: Column(
                          children: [
                            IgnorePointer(
                              ignoring: _tenant == null || _step == 2,
                              child: Column(
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: _PremiumField(
                                          label: 'First Name',
                                          hint: 'First name',
                                          icon: Icons.person_outline_rounded,
                                          primary: primary,
                                          controller: _firstNameController,
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: _PremiumField(
                                          label: 'Last Name',
                                          hint: 'Last name',
                                          icon: Icons.badge_outlined,
                                          primary: primary,
                                          controller: _lastNameController,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 16),
                                  Row(
                                    children: [
                                      Expanded(
                                        child: _PremiumField(
                                          label: 'Middle Name',
                                          hint: 'Optional',
                                          icon: Icons.short_text_rounded,
                                          primary: primary,
                                          controller: _middleNameController,
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: _PremiumField(
                                          label: 'Suffix',
                                          hint: 'Optional',
                                          icon: Icons.verified_user_outlined,
                                          primary: primary,
                                          controller: _suffixController,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 16),
                                  _PremiumField(
                                    label: 'Real Email',
                                    hint: 'name@example.com',
                                    icon: Icons.email_outlined,
                                    primary: primary,
                                    controller: _emailController,
                                    keyboardType: TextInputType.emailAddress,
                                  ),
                                  const SizedBox(height: 16),
                                  _UsernameField(
                                    controller: _usernameController,
                                    tenantSuffix: _tenant?.slug ?? 'tenant',
                                    primary: primary,
                                  ),
                                  const SizedBox(height: 8),
                                  if (_loginPreview.isNotEmpty)
                                    Align(
                                      alignment: Alignment.centerLeft,
                                      child: Text(
                                        'Final username: $_loginPreview',
                                        style: GoogleFonts.inter(
                                          color: AppColors.textMuted,
                                          fontSize: 12.5,
                                        ),
                                      ),
                                    ),
                                  const SizedBox(height: 16),
                                  _PremiumField(
                                    label: 'Password',
                                    hint: 'Create password',
                                    icon: Icons.lock_outline_rounded,
                                    primary: primary,
                                    controller: _passwordController,
                                    obscureText: _obscurePassword,
                                    suffixIcon: IconButton(
                                      onPressed: () {
                                        setState(() {
                                          _obscurePassword = !_obscurePassword;
                                        });
                                      },
                                      icon: Icon(
                                        _obscurePassword
                                            ? Icons.visibility_off_outlined
                                            : Icons.visibility_outlined,
                                        color: AppColors.textMuted,
                                        size: 20,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  _PasswordStrengthMeter(
                                    strength: _passwordStrength,
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    'At least 12 chars, mixed case, number and symbol for the strongest grade.',
                                    style: GoogleFonts.inter(
                                      fontSize: 11,
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                                  const SizedBox(height: 16),
                                  _PremiumField(
                                    label: 'Confirm Password',
                                    hint: 'Confirm password',
                                    icon: Icons.lock_reset_rounded,
                                    primary: primary,
                                    controller: _confirmPasswordController,
                                    obscureText: _obscureConfirmPassword,
                                    suffixIcon: IconButton(
                                      onPressed: () {
                                        setState(() {
                                          _obscureConfirmPassword =
                                              !_obscureConfirmPassword;
                                        });
                                      },
                                      icon: Icon(
                                        _obscureConfirmPassword
                                            ? Icons.visibility_off_outlined
                                            : Icons.visibility_outlined,
                                        color: AppColors.textMuted,
                                        size: 20,
                                      ),
                                    ),
                                  ),
                                  if (_step == 1) ...[
                                    const SizedBox(height: 24),
                                    _PremiumButton(
                                      label: 'Create Account',
                                      isLoading: _isLoading,
                                      primary: primary,
                                      secondary: secondary,
                                      onPressed: _register,
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            if (_step == 2) ...[
                              const SizedBox(height: 24),
                              _LoginUsernameCard(
                                loginUsername: _finalLoginUsername,
                              ),
                              const SizedBox(height: 16),
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(18),
                                decoration: BoxDecoration(
                                  color: primary.withOpacity(0.06),
                                  borderRadius: BorderRadius.circular(20),
                                  border: Border.all(
                                    color: primary.withOpacity(0.14),
                                  ),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Verification Code',
                                      style: GoogleFonts.outfit(
                                        fontSize: 18,
                                        fontWeight: FontWeight.w800,
                                        color: AppColors.textMain,
                                      ),
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      'Enter the 6-digit code sent to ${_emailController.text.trim()}. Verification starts automatically after the 6th digit.',
                                      style: GoogleFonts.inter(
                                        fontSize: 13,
                                        color: AppColors.textMuted,
                                        height: 1.5,
                                      ),
                                    ),
                                    const SizedBox(height: 14),
                                    _PremiumField(
                                      label: 'OTP',
                                      hint: 'Enter 6-digit code',
                                      icon: Icons.pin_outlined,
                                      primary: primary,
                                      controller: _otpController,
                                      keyboardType: TextInputType.number,
                                      enabled: !_isLoading,
                                      maxLength: 6,
                                      inputFormatters: [
                                        FilteringTextInputFormatter.digitsOnly,
                                      ],
                                    ),
                                    if (_isLoading) ...[
                                      const SizedBox(height: 12),
                                      Row(
                                        children: [
                                          SizedBox(
                                            width: 16,
                                            height: 16,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2.2,
                                              color: primary,
                                            ),
                                          ),
                                          const SizedBox(width: 10),
                                          Text(
                                            'Verifying code...',
                                            style: GoogleFonts.inter(
                                              fontSize: 12.5,
                                              fontWeight: FontWeight.w600,
                                              color: AppColors.textMain,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            ],
                            const SizedBox(height: 20),
                            Center(
                              child: Text.rich(
                                TextSpan(
                                  text: 'Already have an account? ',
                                  style: GoogleFonts.inter(
                                    fontSize: 13,
                                    color: AppColors.textMuted,
                                  ),
                                  children: [
                                    TextSpan(
                                      text: 'Log In',
                                      style: TextStyle(
                                        color: primary,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ] else ...[
                      Center(
                        child: Container(
                          width: 78,
                          height: 78,
                          decoration: BoxDecoration(
                            color: const Color(0xFFDCFCE7),
                            borderRadius: BorderRadius.circular(28),
                          ),
                          child: const Icon(
                            Icons.check_circle_outline_rounded,
                            color: Color(0xFF16A34A),
                            size: 40,
                          ),
                        ),
                      ),
                      const SizedBox(height: 18),
                      _LoginUsernameCard(loginUsername: _finalLoginUsername),
                      const SizedBox(height: 24),
                      _PremiumButton(
                        label: 'Continue to Sign In',
                        isLoading: false,
                        primary: primary,
                        secondary: secondary,
                        onPressed: () {
                          if (_tenant == null) {
                            Navigator.of(context).pop();
                            return;
                          }
                          Navigator.of(context).pop(
                            _RegistrationCompletion(
                              loginUsername: _finalLoginUsername,
                              tenant: _tenant!.tenant,
                            ),
                          );
                        },
                      ),
                    ],
                    const SizedBox(height: 32),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    ),
    );
  }

}

class _ForgotPasswordModal extends StatefulWidget {
  const _ForgotPasswordModal();

  @override
  State<_ForgotPasswordModal> createState() => _ForgotPasswordModalState();
}

class _ForgotPasswordModalState extends State<_ForgotPasswordModal> {
  final _loginUsernameController = TextEditingController();
  final _recoveryEmailController = TextEditingController();
  final _codeController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  bool _isLoading = false;
  bool _showFindAccount = false;
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;
  int _step = 1;
  int _passwordStrength = 0;
  String? _errorMessage;
  String? _infoMessage;
  List<Map<String, dynamic>> _accounts = <Map<String, dynamic>>[];

  @override
  void initState() {
    super.initState();
    _newPasswordController.addListener(_handlePasswordStrengthChange);
  }

  void _handlePasswordStrengthChange() {
    final strength = _calculatePasswordStrength(_newPasswordController.text);
    if (_passwordStrength != strength && mounted) {
      setState(() => _passwordStrength = strength);
    }
  }

  @override
  void dispose() {
    _loginUsernameController.dispose();
    _recoveryEmailController.dispose();
    _codeController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  Future<void> _sendResetCode() async {
    final loginUsername = _loginUsernameController.text.trim();
    if (loginUsername.isEmpty || !loginUsername.contains('@')) {
      setState(() {
        _errorMessage =
            'Enter the exact username in the format username@institution.';
        _infoMessage = null;
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _infoMessage = null;
    });

    try {
      final response = await http
          .post(
            Uri.parse(ApiConfig.getUrl('api_forgot_password.php')),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({'login_username': loginUsername}),
          )
          .timeout(const Duration(seconds: 20));

      final data = _decodeApiMap(response.body);
      if (data['success'] == true) {
        setState(() {
          _step = 2;
          _infoMessage = _cleanString(data['message']).isEmpty
              ? 'Reset code sent.'
              : _cleanString(data['message']);
          final returnedUsername = _cleanString(data['login_username']);
          if (returnedUsername.isNotEmpty) {
            _loginUsernameController.text = returnedUsername;
          }
        });
      } else {
        setState(() {
          _errorMessage = _cleanString(data['message']).isEmpty
              ? 'Unable to send the reset code.'
              : _cleanString(data['message']);
        });
      }
    } catch (_) {
      setState(() {
        _errorMessage = 'Failed to connect to the server.';
      });
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _verifyResetCode() async {
    if (_codeController.text.trim().isEmpty) {
      setState(() {
        _errorMessage = 'Enter the reset code.';
        _infoMessage = null;
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _infoMessage = null;
    });

    try {
      final response = await http
          .post(
            Uri.parse(ApiConfig.getUrl('api_verify_otp.php')),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'login_username': _loginUsernameController.text.trim(),
              'reset_code': _codeController.text.trim(),
            }),
          )
          .timeout(const Duration(seconds: 20));

      final data = _decodeApiMap(response.body);
      if (data['success'] == true) {
        setState(() {
          _step = 3;
          _infoMessage = _cleanString(data['message']).isEmpty
              ? 'Reset code verified.'
              : _cleanString(data['message']);
          final returnedUsername = _cleanString(data['login_username']);
          if (returnedUsername.isNotEmpty) {
            _loginUsernameController.text = returnedUsername;
          }
        });
      } else {
        setState(() {
          _errorMessage = _cleanString(data['message']).isEmpty
              ? 'Unable to verify the reset code.'
              : _cleanString(data['message']);
        });
      }
    } catch (_) {
      setState(() {
        _errorMessage = 'Failed to connect to the server.';
      });
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _resetPassword() async {
    if (_newPasswordController.text.length < 8) {
      setState(() {
        _errorMessage = 'Use a password with at least 8 characters.';
        _infoMessage = null;
      });
      return;
    }

    if (_newPasswordController.text != _confirmPasswordController.text) {
      setState(() {
        _errorMessage = 'Passwords do not match.';
        _infoMessage = null;
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _infoMessage = null;
    });

    try {
      final response = await http
          .post(
            Uri.parse(ApiConfig.getUrl('api_reset_password.php')),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({
              'login_username': _loginUsernameController.text.trim(),
              'reset_code': _codeController.text.trim(),
              'new_password': _newPasswordController.text,
            }),
          )
          .timeout(const Duration(seconds: 20));

      final data = _decodeApiMap(response.body);
      if (data['success'] == true) {
        if (!mounted) {
          return;
        }
        final messenger = ScaffoldMessenger.of(context);
        messenger.showSnackBar(
          SnackBar(
            content: Text(
              'Password reset for ${_loginUsernameController.text.trim()}.',
            ),
          ),
        );
        Navigator.of(context).pop();
      } else {
        setState(() {
          _errorMessage = _cleanString(data['message']).isEmpty
              ? 'Unable to reset the password.'
              : _cleanString(data['message']);
        });
      }
    } catch (_) {
      setState(() {
        _errorMessage = 'Failed to connect to the server.';
      });
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _findAccounts() async {
    final email = _recoveryEmailController.text.trim();
    if (email.isEmpty || !email.contains('@')) {
      setState(() {
        _errorMessage = 'Enter the real email linked to your account.';
        _infoMessage = null;
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _infoMessage = null;
      _accounts = <Map<String, dynamic>>[];
    });

    try {
      final response = await http
          .post(
            Uri.parse(ApiConfig.getUrl('api_find_accounts.php')),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({'email': email}),
          )
          .timeout(const Duration(seconds: 20));

      final data = _decodeApiMap(response.body);
      if (data['success'] == true) {
        setState(() {
          _infoMessage = _cleanString(data['message']).isEmpty
              ? 'If that email is linked to an account, recovery details are on the way.'
              : _cleanString(data['message']);
          _accounts = (data['accounts'] as List? ?? const [])
              .map((item) => _stringMap(item))
              .where((item) => item.isNotEmpty)
              .toList();
        });
      } else {
        setState(() {
          _errorMessage = _cleanString(data['message']).isEmpty
              ? 'Unable to look up accounts.'
              : _cleanString(data['message']);
        });
      }
    } catch (_) {
      setState(() {
        _errorMessage = 'Failed to connect to the server.';
      });
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  void _useRecoveredUsername(String loginUsername) {
    setState(() {
      _showFindAccount = false;
      _loginUsernameController.text = loginUsername;
      _accounts = <Map<String, dynamic>>[];
      _errorMessage = null;
      _infoMessage =
          'Recovered username loaded. Continue with Forgot Password using $loginUsername.';
    });
  }

  Widget _buildLegacyForgotPasswordSheet(
    BuildContext context, {
    required Color primary,
    required Color secondary,
    required double bottomInset,
  }) {
    final stepTitles = [
      'Forgot Password',
      'Verify Code',
      'Reset Password',
    ];
    final stepSubs = [
      'Enter your exact username to receive a reset code.',
      'Enter the 6-digit code sent to the real email linked to that username.',
      'Choose a strong new password for this account.',
    ];

    return Container(
      margin: EdgeInsets.only(top: MediaQuery.of(context).padding.top + 100),
      padding: EdgeInsets.fromLTRB(24, 20, 24, bottomInset + 24),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      child: Material(
        color: Colors.transparent,
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 44,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE2E8F0),
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              if (!_showFindAccount) ...[
                Row(
                  children: List.generate(
                    3,
                    (index) => Container(
                      width: index == (_step - 1) ? 24 : 8,
                      height: 8,
                      margin: const EdgeInsets.only(right: 6),
                      decoration: BoxDecoration(
                        color: index <= (_step - 1)
                            ? primary
                            : primary.withOpacity(0.25),
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
              ],
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _showFindAccount
                              ? 'Find My Account'
                              : stepTitles[_step - 1],
                          style: GoogleFonts.outfit(
                            fontSize: 24,
                            fontWeight: FontWeight.w800,
                            color: AppColors.textMain,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _showFindAccount
                              ? 'Enter your real email and the app will email every linked username.'
                              : stepSubs[_step - 1],
                          style: GoogleFonts.inter(
                            color: AppColors.textMuted,
                            fontSize: 14,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: Icon(
                      Icons.close_rounded,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              if (_errorMessage != null)
                _StatusBanner(message: _errorMessage!, isError: true),
              if (_infoMessage != null)
                _StatusBanner(message: _infoMessage!, isError: false),
              if (_showFindAccount) ...[
                _PremiumField(
                  label: 'Real Email',
                  hint: 'name@example.com',
                  icon: Icons.email_outlined,
                  primary: primary,
                  controller: _recoveryEmailController,
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: 24),
                _PremiumButton(
                  label: 'Email My Usernames',
                  isLoading: _isLoading,
                  primary: primary,
                  secondary: secondary,
                  onPressed: _findAccounts,
                ),
                if (_accounts.isNotEmpty) ...[
                  const SizedBox(height: 18),
                  ..._accounts.map((account) {
                    final loginUsername =
                        _cleanString(account['login_username']);
                    final tenantName = _cleanString(account['tenant_name']);
                    return Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: AppColors.border),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  loginUsername,
                                  style: GoogleFonts.inter(
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.textMain,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  tenantName,
                                  style: GoogleFonts.inter(
                                    color: AppColors.textMuted,
                                    fontSize: 12.5,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          TextButton(
                            onPressed: () => _useRecoveredUsername(loginUsername),
                            child: Text(
                              'Use this',
                              style: TextStyle(
                                color: primary,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  }),
                ],
                const SizedBox(height: 4),
                Center(
                  child: TextButton(
                    onPressed: () {
                      setState(() {
                        _showFindAccount = false;
                        _errorMessage = null;
                        _infoMessage = null;
                        _accounts = <Map<String, dynamic>>[];
                      });
                    },
                    child: Text(
                      'I remember my username',
                      style: TextStyle(
                        color: primary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ] else if (_step == 1) ...[
                _PremiumField(
                  label: 'Username',
                  hint: 'username@institution',
                  icon: Icons.alternate_email_rounded,
                  primary: primary,
                  controller: _loginUsernameController,
                  keyboardType: TextInputType.emailAddress,
                ),
                const SizedBox(height: 24),
                _PremiumButton(
                  label: 'Send Reset Code',
                  isLoading: _isLoading,
                  primary: primary,
                  secondary: secondary,
                  onPressed: _sendResetCode,
                ),
                Center(
                  child: TextButton(
                    onPressed: () {
                      setState(() {
                        _showFindAccount = true;
                        _errorMessage = null;
                        _infoMessage = null;
                      });
                    },
                    child: Text(
                      'Forgot your username too?',
                      style: TextStyle(
                        color: primary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ] else if (_step == 2) ...[
                _LoginUsernameCard(
                  loginUsername: _loginUsernameController.text.trim(),
                ),
                const SizedBox(height: 16),
                _PremiumField(
                  label: 'Reset Code',
                  hint: 'Enter 6-digit code',
                  icon: Icons.pin_outlined,
                  primary: primary,
                  controller: _codeController,
                  keyboardType: TextInputType.number,
                ),
                const SizedBox(height: 24),
                _PremiumButton(
                  label: 'Verify Code',
                  isLoading: _isLoading,
                  primary: primary,
                  secondary: secondary,
                  onPressed: _verifyResetCode,
                ),
              ] else ...[
                _LoginUsernameCard(
                  loginUsername: _loginUsernameController.text.trim(),
                ),
                const SizedBox(height: 16),
                _PremiumField(
                  label: 'New Password',
                  hint: 'Enter new password',
                  icon: Icons.lock_outline_rounded,
                  primary: primary,
                  controller: _newPasswordController,
                  obscureText: _obscurePassword,
                  suffixIcon: IconButton(
                    onPressed: () {
                      setState(() {
                        _obscurePassword = !_obscurePassword;
                      });
                    },
                    icon: Icon(
                      _obscurePassword
                          ? Icons.visibility_off_outlined
                          : Icons.visibility_outlined,
                      color: AppColors.textMuted,
                      size: 20,
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                _PasswordStrengthMeter(strength: _passwordStrength),
                const SizedBox(height: 6),
                Text(
                  'At least 12 chars, mixed case, number and symbol for the strongest grade.',
                  style: GoogleFonts.inter(
                    fontSize: 11,
                    color: AppColors.textMuted,
                  ),
                ),
                const SizedBox(height: 16),
                _PremiumField(
                  label: 'Confirm Password',
                  hint: 'Confirm new password',
                  icon: Icons.lock_reset_rounded,
                  primary: primary,
                  controller: _confirmPasswordController,
                  obscureText: _obscureConfirmPassword,
                  suffixIcon: IconButton(
                    onPressed: () {
                      setState(() {
                        _obscureConfirmPassword =
                            !_obscureConfirmPassword;
                      });
                    },
                    icon: Icon(
                      _obscureConfirmPassword
                          ? Icons.visibility_off_outlined
                          : Icons.visibility_outlined,
                      color: AppColors.textMuted,
                      size: 20,
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                _PremiumButton(
                  label: 'Reset Password',
                  isLoading: _isLoading,
                  primary: primary,
                  secondary: secondary,
                  onPressed: _resetPassword,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;
    final secondary = tenant.themeSecondaryColor;
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return _buildLegacyForgotPasswordSheet(
      context,
      primary: primary,
      secondary: secondary,
      bottomInset: bottomInset,
    );
  }
}

class _QrScannerSheet extends StatefulWidget {
  const _QrScannerSheet();

  @override
  State<_QrScannerSheet> createState() => _QrScannerSheetState();
}

class _QrScannerSheetState extends State<_QrScannerSheet> {
  late final MobileScannerController _controller;
  bool _didResolve = false;

  @override
  void initState() {
    super.initState();
    _controller = MobileScannerController(
      autoStart: true,
      detectionSpeed: DetectionSpeed.noDuplicates,
      formats: const [BarcodeFormat.qrCode],
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _handleCapture(BarcodeCapture capture) {
    if (_didResolve) {
      return;
    }

    final payload = capture.barcodes
        .map((barcode) => barcode.rawValue?.trim() ?? '')
        .firstWhere((value) => value.isNotEmpty, orElse: () => '');

    if (payload.isEmpty) {
      return;
    }

    _didResolve = true;
    Navigator.of(context).pop(payload);
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 84),
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
      decoration: const BoxDecoration(
        color: Colors.black,
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 44,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.white24,
                borderRadius: BorderRadius.circular(999),
              ),
            ),
          ),
          const SizedBox(height: 18),
          Text(
            'Scan Tenant QR',
            style: GoogleFonts.outfit(
              color: Colors.white,
              fontSize: 26,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Point the camera at your institution QR code to extract the tenant reference.',
            style: GoogleFonts.inter(
              color: Colors.white70,
              fontSize: 13.5,
              height: 1.55,
            ),
          ),
          const SizedBox(height: 18),
          ClipRRect(
            borderRadius: BorderRadius.circular(26),
            child: SizedBox(
              height: 340,
              child: MobileScanner(
                controller: _controller,
                onDetect: _handleCapture,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ResolvedTenant {
  final TenantBranding tenant;
  final String token;
  final String name;
  final String slug;

  const _ResolvedTenant({
    required this.tenant,
    required this.token,
    required this.name,
    required this.slug,
  });

  factory _ResolvedTenant.fromResponse(Map<String, dynamic> response) {
    final tenantPayload = _stringMap(response['tenant']);
    final tenantBranding = TenantBranding.fromJson(tenantPayload);
    return _ResolvedTenant(
      tenant: tenantBranding,
      token: _cleanString(response['tenant_context_token']),
      name: _cleanString(tenantPayload['tenant_name']).isEmpty
          ? tenantBranding.appName
          : _cleanString(tenantPayload['tenant_name']),
      slug: _cleanString(tenantPayload['tenant_slug']),
    );
  }
}

class _RegistrationCompletion {
  final String loginUsername;
  final TenantBranding tenant;

  const _RegistrationCompletion({
    required this.loginUsername,
    required this.tenant,
  });
}

class _LegacyLoginField extends StatefulWidget {
  final String hint;
  final TextEditingController controller;
  final Color primary;
  final bool obscureText;
  final Widget? suffixIcon;
  final String? Function(String?)? validator;
  final TextInputType? keyboardType;

  final void Function(String)? onChanged;

  const _LegacyLoginField({
    required this.hint,
    required this.controller,
    required this.primary,
    this.obscureText = false,
    this.suffixIcon,
    this.validator,
    this.keyboardType,
    this.onChanged,
  });

  @override
  State<_LegacyLoginField> createState() => _LegacyLoginFieldState();
}

class _LegacyLoginFieldState extends State<_LegacyLoginField> {
  bool _focused = false;

  @override
  Widget build(BuildContext context) {
    return Focus(
      onFocusChange: (focused) => setState(() => _focused = focused),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: _focused
                ? widget.primary.withOpacity(0.55)
                : const Color(0xFFE8EDF3),
            width: _focused ? 1.8 : 1.2,
          ),
          boxShadow: _focused
              ? [
                  BoxShadow(
                    color: widget.primary.withOpacity(0.1),
                    blurRadius: 10,
                    offset: const Offset(0, 3),
                  ),
                ]
              : const [],
        ),
        child: TextFormField(
          controller: widget.controller,
          obscureText: widget.obscureText,
          keyboardType: widget.keyboardType,
          validator: widget.validator,
          onChanged: widget.onChanged,
          style: GoogleFonts.inter(
            fontSize: 15,
            fontWeight: FontWeight.w500,
            color: const Color(0xFF1A1A2E),
          ),
          decoration: InputDecoration(
            hintText: widget.hint,
            hintStyle: GoogleFonts.inter(
              fontSize: 14,
              color: const Color(0xFFADB5BD),
            ),
            suffixIcon: widget.suffixIcon != null
                ? Padding(
                    padding: const EdgeInsets.only(right: 14),
                    child: widget.suffixIcon,
                  )
                : null,
            suffixIconConstraints: const BoxConstraints(
              minWidth: 0,
              minHeight: 0,
            ),
            filled: true,
            fillColor: _focused
                ? widget.primary.withOpacity(0.03)
                : const Color(0xFFF8FAFC),
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 18,
              vertical: 18,
            ),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: BorderSide.none,
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: BorderSide.none,
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: BorderSide.none,
            ),
            errorBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(
                color: AppColors.error,
                width: 1.2,
              ),
            ),
            focusedErrorBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(
                color: AppColors.error,
                width: 1.5,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _PremiumField extends StatefulWidget {
  final String label;
  final String hint;
  final IconData icon;
  final Color primary;
  final TextEditingController controller;
  final bool enabled;
  final bool obscureText;
  final Widget? suffixIcon;
  final String? Function(String?)? validator;
  final TextInputType? keyboardType;
  final int? maxLength;
  final List<TextInputFormatter>? inputFormatters;
  final ValueChanged<String>? onChanged;

  const _PremiumField({
    required this.label,
    required this.hint,
    required this.icon,
    required this.primary,
    required this.controller,
    this.enabled = true,
    this.obscureText = false,
    this.suffixIcon,
    this.validator,
    this.keyboardType,
    this.maxLength,
    this.inputFormatters,
    this.onChanged,
  });

  @override
  State<_PremiumField> createState() => _PremiumFieldState();
}

class _PremiumFieldState extends State<_PremiumField> {
  bool _focused = false;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          widget.label,
          style: GoogleFonts.inter(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: _focused
                ? widget.primary
                : AppColors.textMain.withOpacity(0.75),
          ),
        ),
        const SizedBox(height: 8),
        Focus(
          onFocusChange: (focused) => setState(() => _focused = focused),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                color: _focused
                    ? widget.primary.withOpacity(0.6)
                    : const Color(0xFFE8EDF3),
                width: _focused ? 1.8 : 1.2,
              ),
              boxShadow: _focused
                  ? [
                      BoxShadow(
                        color: widget.primary.withOpacity(0.12),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ]
                  : const [],
            ),
            child: TextFormField(
              controller: widget.controller,
              enabled: widget.enabled,
              obscureText: widget.obscureText,
              keyboardType: widget.keyboardType,
              validator: widget.validator,
              maxLength: widget.maxLength,
              inputFormatters: widget.inputFormatters,
              onChanged: widget.onChanged,
              style: GoogleFonts.inter(
                fontSize: 15,
                fontWeight: FontWeight.w500,
                color: AppColors.textMain,
              ),
              decoration: InputDecoration(
                hintText: widget.hint,
                hintStyle: GoogleFonts.inter(
                  fontSize: 14,
                  color: AppColors.textMuted.withOpacity(0.5),
                ),
                prefixIcon: Padding(
                  padding: const EdgeInsets.only(left: 14, right: 10),
                  child: Icon(
                    widget.icon,
                    size: 20,
                    color: _focused ? widget.primary : AppColors.textMuted,
                  ),
                ),
                prefixIconConstraints: const BoxConstraints(
                  minWidth: 0,
                  minHeight: 0,
                ),
                suffixIcon: widget.suffixIcon,
                filled: true,
                fillColor: widget.enabled
                    ? (_focused
                        ? widget.primary.withOpacity(0.03)
                        : const Color(0xFFF9FAFC))
                    : const Color(0xFFF1F5F9),
                counterText: widget.maxLength == null ? null : '',
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 18,
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide.none,
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide.none,
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: BorderSide.none,
                ),
                errorBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: const BorderSide(
                    color: AppColors.error,
                    width: 1.2,
                  ),
                ),
                focusedErrorBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(16),
                  borderSide: const BorderSide(
                    color: AppColors.error,
                    width: 1.5,
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _PremiumButton extends StatefulWidget {
  final String label;
  final bool isLoading;
  final Color primary;
  final Color secondary;
  final VoidCallback onPressed;

  const _PremiumButton({
    required this.label,
    required this.isLoading,
    required this.primary,
    required this.secondary,
    required this.onPressed,
  });

  @override
  State<_PremiumButton> createState() => _PremiumButtonState();
}

class _PremiumButtonState extends State<_PremiumButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _pressed = true),
      onTapUp: (_) {
        setState(() => _pressed = false);
        if (!widget.isLoading) {
          widget.onPressed();
        }
      },
      onTapCancel: () => setState(() => _pressed = false),
      child: AnimatedScale(
        scale: _pressed ? 0.97 : 1.0,
        duration: const Duration(milliseconds: 100),
        child: Container(
          width: double.infinity,
          height: 56,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [
                widget.primary,
                Color.lerp(widget.primary, widget.secondary, 0.6)!,
              ],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: widget.primary.withOpacity(0.38),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Center(
            child: widget.isLoading
                ? const SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2.5,
                    ),
                  )
                : Text(
                    widget.label,
                    style: GoogleFonts.outfit(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                      letterSpacing: 0.2,
                    ),
                  ),
          ),
        ),
      ),
    );
  }
}

class _PasswordStrengthMeter extends StatelessWidget {
  final int strength;

  const _PasswordStrengthMeter({required this.strength});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        ...List.generate(
          4,
          (index) => Expanded(
            child: Container(
              height: 4,
              margin: EdgeInsets.only(right: index < 3 ? 4 : 0),
              decoration: BoxDecoration(
                color: _passwordStrengthColor(strength, index),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
        ),
        if (strength > 0) ...[
          const SizedBox(width: 8),
          Text(
            _passwordStrengthLabel(strength),
            style: GoogleFonts.inter(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: _passwordStrengthColor(strength, strength - 1),
            ),
          ),
        ],
      ],
    );
  }
}

class _StatusBanner extends StatelessWidget {
  final String message;
  final bool isError;

  const _StatusBanner({required this.message, required this.isError});

  @override
  Widget build(BuildContext context) {
    final color = isError ? AppColors.error : const Color(0xFF10B981);
    final icon = isError
        ? Icons.error_outline_rounded
        : Icons.check_circle_outline_rounded;

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withOpacity(0.2)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: GoogleFonts.inter(
                color: color,
                fontSize: 13.5,
                height: 1.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AuthTextField extends StatelessWidget {
  final String label;
  final String hint;
  final TextEditingController controller;
  final IconData? prefixIcon;
  final Widget? suffix;
  final bool enabled;
  final bool obscureText;
  final TextInputType? keyboardType;
  final String? Function(String?)? validator;

  const _AuthTextField({
    required this.label,
    required this.hint,
    required this.controller,
    this.prefixIcon,
    this.suffix,
    this.enabled = true,
    this.obscureText = false,
    this.keyboardType,
    this.validator,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.inter(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: AppColors.textMain,
          ),
        ),
        const SizedBox(height: 8),
        TextFormField(
          controller: controller,
          enabled: enabled,
          obscureText: obscureText,
          keyboardType: keyboardType,
          validator: validator,
          decoration: InputDecoration(
            hintText: hint,
            prefixIcon: prefixIcon == null
                ? null
                : Icon(prefixIcon, color: AppColors.primary),
            suffixIcon: suffix,
            fillColor:
                enabled ? const Color(0xFFF8FAFC) : const Color(0xFFF1F5F9),
            filled: true,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide(color: AppColors.border),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide(color: AppColors.border),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(16),
              borderSide: BorderSide(
                color: AppColors.primary.withOpacity(0.7),
                width: 1.5,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _PrimaryAuthButton extends StatelessWidget {
  final String label;
  final bool isLoading;
  final VoidCallback? onPressed;

  const _PrimaryAuthButton({
    required this.label,
    required this.isLoading,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: isLoading ? null : onPressed,
        style: ElevatedButton.styleFrom(
          padding: const EdgeInsets.symmetric(vertical: 18),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
        ),
        child: isLoading
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : Text(
                label,
                style: GoogleFonts.outfit(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
      ),
    );
  }
}

class _TenantReferenceCard extends StatelessWidget {
  final TextEditingController referralController;
  final bool isLoading;
  final _ResolvedTenant? tenant;
  final Color primary;
  final VoidCallback onResolveReferral;
  final VoidCallback onScanQr;
  final VoidCallback onUploadQr;
  final VoidCallback? onChangeTenant;

  const _TenantReferenceCard({
    required this.referralController,
    required this.isLoading,
    required this.tenant,
    required this.primary,
    required this.onResolveReferral,
    required this.onScanQr,
    required this.onUploadQr,
    required this.onChangeTenant,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Tenant Reference',
            style: GoogleFonts.outfit(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: AppColors.textMain,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Choose one: enter the referral code, scan the QR code, or upload a QR image.',
            style: GoogleFonts.inter(
              color: AppColors.textMuted,
              fontSize: 13.5,
              height: 1.55,
            ),
          ),
          const SizedBox(height: 14),
          _AuthTextField(
            label: 'Referral Code',
            hint: 'Enter tenant referral code',
            controller: referralController,
            prefixIcon: Icons.confirmation_number_outlined,
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _InlineActionButton(
                label: 'Validate Referral',
                icon: Icons.verified_outlined,
                primary: primary,
                onPressed: isLoading ? null : onResolveReferral,
              ),
              _InlineActionButton(
                label: 'Scan QR',
                icon: Icons.qr_code_scanner_rounded,
                primary: primary,
                onPressed: isLoading ? null : onScanQr,
              ),
              _InlineActionButton(
                label: 'Upload QR',
                icon: Icons.image_search_rounded,
                primary: primary,
                onPressed: isLoading ? null : onUploadQr,
              ),
            ],
          ),
          if (tenant != null) ...[
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: primary.withOpacity(0.08),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: primary.withOpacity(0.16)),
              ),
                    child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.verified_rounded, color: primary),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          tenant!.name,
                          style: GoogleFonts.outfit(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                            color: AppColors.textMain,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '@${tenant!.slug}',
                          style: GoogleFonts.inter(
                            color: AppColors.textMuted,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (onChangeTenant != null)
                    TextButton(
                      onPressed: onChangeTenant,
                      child: const Text('Change'),
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _UsernameField extends StatelessWidget {
  final TextEditingController controller;
  final String tenantSuffix;
  final Color primary;

  const _UsernameField({
    required this.controller,
    required this.tenantSuffix,
    required this.primary,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Username',
          style: GoogleFonts.inter(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: AppColors.textMain,
          ),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: controller,
                  inputFormatters: [
                    FilteringTextInputFormatter.deny(RegExp(r'@')),
                    FilteringTextInputFormatter.allow(
                      RegExp(r'[A-Za-z0-9._-]'),
                    ),
                  ],
                  decoration: InputDecoration(
                    hintText: 'username',
                    prefixIcon: Icon(
                      Icons.person_outline_rounded,
                      color: primary,
                    ),
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    filled: false,
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 16,
                    ),
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 16,
                ),
                decoration: BoxDecoration(
                  color: primary.withOpacity(0.08),
                  borderRadius: const BorderRadius.only(
                    topRight: Radius.circular(16),
                    bottomRight: Radius.circular(16),
                  ),
                ),
                child: Text(
                  '@$tenantSuffix',
                  style: GoogleFonts.inter(
                    color: AppColors.textMuted,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _LoginUsernameCard extends StatelessWidget {
  final String loginUsername;

  const _LoginUsernameCard({required this.loginUsername});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Username',
            style: GoogleFonts.inter(
              color: AppColors.textMuted,
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            loginUsername,
            style: GoogleFonts.outfit(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: AppColors.textMain,
            ),
          ),
        ],
      ),
    );
  }
}

class _InlineActionButton extends StatelessWidget {
  final String label;
  final IconData icon;
  final Color primary;
  final VoidCallback? onPressed;

  const _InlineActionButton({
    required this.label,
    required this.icon,
    required this.primary,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    final disabled = onPressed == null;
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: disabled ? const Color(0xFFF1F5F9) : primary.withOpacity(0.08),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: disabled ? AppColors.border : primary.withOpacity(0.14),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 18,
              color: disabled ? AppColors.textMuted : primary,
            ),
            const SizedBox(width: 8),
            Text(
              label,
              style: GoogleFonts.inter(
                color: disabled ? AppColors.textMuted : AppColors.textMain,
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
