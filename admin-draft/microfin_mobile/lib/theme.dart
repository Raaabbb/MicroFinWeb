import 'package:flutter/material.dart';
import 'main.dart';

/// Static color constants are removed in favor of strict DB-driven colors.
/// All colors MUST come from activeTenant.value.
class AppColors {
  AppColors._();

  // ─── Surface & Background ───────────────────────────────────────────────────
  static Color get bg => activeTenant.value.themeBgBody;
  static Color get card => activeTenant.value.themeBgCard;

  // ─── Text ───────────────────────────────────────────────────────────────────
  static Color get textMain => activeTenant.value.themeTextMain;
  static Color get textMuted => activeTenant.value.themeTextMuted;

  // ─── Brand Colors ───────────────────────────────────────────────────────────
  static Color get primary => activeTenant.value.themePrimaryColor;
  static Color get secondary => activeTenant.value.themeSecondaryColor;

  // ─── Semantic / Utility ─────────────────────────────────────────────────────
  /// Error / destructive red — used for interest amounts, danger states, etc.
  static const Color error = Color(0xFFEF4444);
  /// Secondary text / muted labels
  static const Color textSecondary = Color(0xFF6B7280);
  /// Thin divider lines inside cards
  static const Color divider = Color(0xFFF3F4F6);

  // ─── Border ─────────────────────────────────────────────────────────────────
  static Color get border => activeTenant.value.themeBorderColor;
  
  // ─── Shadows & Borders ──────────────────────────────────────────────────────
  static List<BoxShadow> get cardShadow => activeTenant.value.cardShadow;
  static double get cardBorderWidth => activeTenant.value.cardBorderWidth;

  /// High-blur soft shadows for premium floating effects
  static List<BoxShadow> get premiumShadow => [
    BoxShadow(
      color: Colors.black.withOpacity(0.04),
      blurRadius: 40,
      offset: const Offset(0, 10),
    ),
    BoxShadow(
      color: primary.withOpacity(0.02),
      blurRadius: 20,
      offset: const Offset(0, 4),
    ),
  ];
}

/// Elite UI Helpers for premium aesthetics
class AppPremium {
  AppPremium._();

  static const double radius = 28.0;

  /// Returns a decoration for modern, non-bordered input fields
  static InputDecoration fieldDecoration({
    required String label,
    String? hint,
    IconData? icon,
    Color? fillColor,
  }) {
    final primary = AppColors.primary;
    return InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: icon != null ? Icon(icon, size: 20, color: AppColors.textMain.withOpacity(0.6)) : null,
      filled: true,
      fillColor: fillColor ?? AppColors.bg.withOpacity(0.4),
      labelStyle: TextStyle(color: AppColors.textMuted, fontWeight: FontWeight.w500, fontSize: 13),
      hintStyle: TextStyle(color: AppColors.textMuted.withOpacity(0.5), fontSize: 13),
      floatingLabelStyle: TextStyle(color: primary, fontWeight: FontWeight.w700, fontSize: 15),
      contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 20),
      // No borders (The "No-Lines" requirement)
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(radius - 8), borderSide: BorderSide.none),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(radius - 8), borderSide: BorderSide.none),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(radius - 8),
        borderSide: BorderSide(color: primary.withOpacity(0.3), width: 1.5),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(radius - 8),
        borderSide: const BorderSide(color: AppColors.error, width: 1),
      ),
    );
  }

  /// Premium card decoration with glass/soft effect
  static BoxDecoration cardDecoration({Color? color, bool hasShadow = true}) {
    return BoxDecoration(
      color: color ?? AppColors.card,
      borderRadius: BorderRadius.circular(radius),
      boxShadow: hasShadow ? AppColors.premiumShadow : null,
    );
  }
}

/// Formatting helpers for the app
class AppFormat {
  AppFormat._();

  static String peso(double amount) {
    final parts = amount.toStringAsFixed(2).split('.');
    final intPart = parts[0];
    final decPart = parts[1];
    final buffer = StringBuffer();
    int count = 0;
    for (int i = intPart.length - 1; i >= 0; i--) {
      if (count > 0 && count % 3 == 0) buffer.write(',');
      buffer.write(intPart[i]);
      count++;
    }
    return '₱${buffer.toString().split('').reversed.join()}.$decPart';
  }

  static String pesoCompact(double amount) {
    if (amount >= 1000000) {
      return '₱${(amount / 1000000).toStringAsFixed(1)}M';
    } else if (amount >= 1000) {
      return '₱${(amount / 1000).toStringAsFixed(1)}K';
    }
    return peso(amount);
  }

  /// Transforms technical keys like 'self_employed' to 'Self Employed'
  static String normalizeLabel(String raw) {
    if (raw.isEmpty) return raw;
    final clean = raw.replaceAll('_', ' ').trim();
    if (clean.isEmpty) return clean;
    return clean.split(' ').map((word) {
      if (word.isEmpty) return '';
      return word[0].toUpperCase() + word.substring(1).toLowerCase();
    }).join(' ');
  }
}


