import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _currentPwdCtrl = TextEditingController();
  final _newPwdCtrl = TextEditingController();
  final _confirmPwdCtrl = TextEditingController();

  bool _isSaving = false;
  bool _obsCurrent = true;
  bool _obsNew = true;
  bool _obsConfirm = true;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    HapticFeedback.mediumImpact();

    setState(() => _isSaving = true);

    try {
      final uId = currentUser.value!['user_id'];
      final tId = activeTenant.value.id;

      final body = jsonEncode({
        'user_id': uId,
        'tenant_id': tId,
        'current_password': _currentPwdCtrl.text,
        'new_password': _newPwdCtrl.text,
      });

      final resp = await http.post(
        Uri.parse(ApiConfig.getUrl('api_change_password.php')),
        headers: {'Content-Type': 'application/json'},
        body: body,
      );

      final data = jsonDecode(resp.body);

      if (data['success'] == true) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Password changed successfully!'),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.pop(context);
      } else {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(data['message'] ?? 'Failed to change password.'),
          ),
        );
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error connecting to server. Please try again later.'),
        ),
      );
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
        title: const Text(
          'Change Password',
          style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.bold,
            fontSize: 18,
          ),
        ),
        iconTheme: const IconThemeData(color: Colors.white),
        elevation: 0,
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        physics: const BouncingScrollPhysics(),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppColors.card,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.border),
                  boxShadow: AppColors.cardShadow,
                ),
                child: Column(
                  children: [
                    Icon(
                      Icons.lock_reset_rounded,
                      size: 54,
                      color: AppColors.textMuted,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Create a strong password with at least 8 characters to keep your account secure.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: AppColors.textMain,
                        fontSize: 13,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 24),

                    _passwordField(
                      'Current Password',
                      _currentPwdCtrl,
                      _obsCurrent,
                      () => setState(() => _obsCurrent = !_obsCurrent),
                    ),
                    const SizedBox(height: 16),
                    _passwordField(
                      'New Password',
                      _newPwdCtrl,
                      _obsNew,
                      () => setState(() => _obsNew = !_obsNew),
                      isNew: true,
                    ),
                    const SizedBox(height: 16),
                    _passwordField(
                      'Confirm New Password',
                      _confirmPwdCtrl,
                      _obsConfirm,
                      () => setState(() => _obsConfirm = !_obsConfirm),
                      isConfirm: true,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 40),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isSaving ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: primary,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                  ),
                  child: _isSaving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2,
                          ),
                        )
                      : const Text(
                          'Change Password',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                            color: Colors.white,
                          ),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _passwordField(
    String label,
    TextEditingController ctrl,
    bool obs,
    VoidCallback toggle, {
    bool isNew = false,
    bool isConfirm = false,
  }) {
    return TextFormField(
      controller: ctrl,
      obscureText: obs,
      style: TextStyle(fontSize: 14, color: AppColors.textMain),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: TextStyle(fontSize: 13, color: AppColors.textMuted),
        prefixIcon: Icon(
          Icons.lock_outline_rounded,
          size: 18,
          color: AppColors.textMuted,
        ),
        suffixIcon: IconButton(
          icon: Icon(
            obs ? Icons.visibility_off_outlined : Icons.visibility_outlined,
            size: 18,
            color: AppColors.textMuted,
          ),
          onPressed: toggle,
        ),
      ),
      validator: (v) {
        if (v == null || v.isEmpty) return 'Required';
        if (isNew && v.length < 8) return 'Minimum 8 characters';
        if (isConfirm && v != _newPwdCtrl.text) return 'Passwords do not match';
        return null;
      },
    );
  }

  @override
  void dispose() {
    _currentPwdCtrl.dispose();
    _newPwdCtrl.dispose();
    _confirmPwdCtrl.dispose();
    super.dispose();
  }
}
