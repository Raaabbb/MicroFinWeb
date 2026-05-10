import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../main.dart';
import '../models/tenant_branding.dart';
import '../theme.dart';
import '../widgets/microfin_logo.dart';
import 'login_screen.dart';

/// Splash screen – the first landing page of the app.
///
/// This redesign focuses on a premium, dynamic UI while keeping the original
/// navigation logic intact. It introduces:
///   • A moving radial gradient background.
///   • Glass‑morphic cards for the feature flow description.
///   • A subtle scaling animation for the logo.
///   • Gradient‑bordered action buttons with smooth hover effects.
///   • Updated typography using Google Fonts (Outfit & Inter).
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _fadeAnimation;
  late final Animation<Offset> _slideAnimation;
  late final Animation<double> _logoScaleAnimation;

  @override
  void initState() {
    super.initState();
    currentUser.value = null;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      if (activeTenant.value.id != TenantBranding.defaultTenant.id) {
        activeTenant.value = TenantBranding.defaultTenant;
      }
    });

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..forward();
    _fadeAnimation = CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic);
    _slideAnimation = Tween<Offset>(
      begin: const Offset(0, 0.08),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic));
    _logoScaleAnimation = Tween<double>(begin: 0.8, end: 1.0).animate(
      CurvedAnimation(parent: _controller, curve: Curves.elasticOut),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _openLogin({required bool openRegistration}) {
    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 350),
        pageBuilder: (_, __, ___) => LoginScreen(openRegistrationOnLoad: openRegistration),
        transitionsBuilder: (_, animation, __, child) => FadeTransition(opacity: animation, child: child),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final primary = AppColors.primary;
    final secondary = AppColors.secondary;

    return Scaffold(
      body: Stack(
        children: [
          // Animated radial gradient background
          AnimatedContainer(
            duration: const Duration(seconds: 6),
            decoration: BoxDecoration(
              gradient: RadialGradient(
                center: const Alignment(-0.3, -0.4),
                radius: 1.2,
                colors: [
                  primary,
                  Color.lerp(primary, secondary, 0.6) ?? secondary,
                  secondary,
                ],
                stops: const [0.0, 0.6, 1.0],
              ),
            ),
          ),
          SafeArea(
            child: FadeTransition(
              opacity: _fadeAnimation,
              child: SlideTransition(
                position: _slideAnimation,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Spacer(),
                      // Logo with scaling animation and glass effect
                      Center(
                        child: ScaleTransition(
                          scale: _logoScaleAnimation,
                          child: Container(
                            width: 96,
                            height: 96,
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.12),
                              borderRadius: BorderRadius.circular(30),
                              border: Border.all(color: Colors.white.withOpacity(0.16)),
                            ),
                            child: const MicroFinLogo(
                              size: 72,
                              elevated: false,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 32),
                      // Main headline
                      Text(
                        'Your Digital Gateway to Financial Growth.',
                        style: GoogleFonts.outfit(
                          color: Colors.white,
                          fontSize: 38,
                          fontWeight: FontWeight.w800,
                          height: 1.1,
                          letterSpacing: -0.8,
                        ),
                      ),
                      const SizedBox(height: 16),
                      // Sub‑headline
                      Text(
                        'Secure access to instant credit, real-time portfolio tracking, and professional financial management.',
                        style: GoogleFonts.inter(
                          color: Colors.white.withOpacity(0.86),
                          fontSize: 15,
                          height: 1.6,
                        ),
                      ),
                      const SizedBox(height: 28),
                      // Glass‑morphic flow cards
                      _GlassFlowCard(
                        title: 'Fast Loan Applications',
                        copy: 'Apply for credit in minutes with our streamlined digital process.',
                        icon: Icons.speed_rounded,
                      ),
                      const SizedBox(height: 12),
                      _GlassFlowCard(
                        title: 'Real-time Tracking',
                        copy: 'Stay updated on your loan status and repayment schedule anytime.',
                        icon: Icons.account_balance_wallet_rounded,
                      ),
                      const SizedBox(height: 12),
                      _GlassFlowCard(
                        title: 'Secure Account',
                        copy: 'Your financial data is protected with advanced encryption and biometrics.',
                        icon: Icons.security_rounded,
                      ),
                      const Spacer(),
                      // Action buttons
                      _SplashActionButton(
                        label: 'Sign In',
                        filled: true,
                        onPressed: () => _openLogin(openRegistration: false),
                      ),
                      const SizedBox(height: 12),
                      _SplashActionButton(
                        label: 'Create Account',
                        filled: false,
                        onPressed: () => _openLogin(openRegistration: true),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Glass‑morphic card used on the splash screen to describe the user flow.
class _GlassFlowCard extends StatelessWidget {
  final String title;
  final String copy;
  final IconData icon;

  const _GlassFlowCard({
    required this.title,
    required this.copy,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
        child: Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: Colors.white.withOpacity(0.08),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: Colors.white.withOpacity(0.12)),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.14),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: Colors.white, size: 24),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: GoogleFonts.outfit(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      copy,
                      style: GoogleFonts.inter(
                        color: Colors.white.withOpacity(0.78),
                        fontSize: 13,
                        height: 1.55,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Action button used at the bottom of the splash screen.
class _SplashActionButton extends StatelessWidget {
  final String label;
  final bool filled;
  final VoidCallback onPressed;

  const _SplashActionButton({
    required this.label,
    required this.filled,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: filled ? Colors.white : Colors.transparent,
          foregroundColor: filled ? AppColors.primary : Colors.white,
          elevation: 0,
          side: filled ? null : BorderSide(color: Colors.white.withOpacity(0.48)),
          padding: const EdgeInsets.symmetric(vertical: 18),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
        ),
        child: Text(
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
