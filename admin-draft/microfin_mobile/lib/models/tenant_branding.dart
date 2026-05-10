import 'package:flutter/material.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../utils/api_config.dart';

/// Holds all branding data for a single tenant.
/// Fetched from the database via the API.
class TenantBranding {
  final String id;
  final String slug;
  final String appName;
  final String logoPath;
  final String fontFamily;
  final Color themePrimaryColor;
  final Color themeSecondaryColor;
  final Color themeTextMain;
  final Color themeTextMuted;
  final Color themeBgBody;
  final Color themeBgCard;
  final Color themeBorderColor;
  final double cardBorderWidth;
  final String cardShadowStr;

  TenantBranding({
    required this.id,
    required this.slug,
    required this.appName,
    required this.logoPath,
    required this.fontFamily,
    required this.themePrimaryColor,
    required this.themeSecondaryColor,
    required this.themeTextMain,
    required this.themeTextMuted,
    required this.themeBgBody,
    required this.themeBgCard,
    required this.themeBorderColor,
    required this.cardBorderWidth,
    required this.cardShadowStr,
  });

  // Dynamic tenants list
  static List<TenantBranding> tenants = [];
  static bool lastLoadSucceeded = false;
  static String? lastLoadError;

  /// Parse hex color (e.g., "#dc2626") to Flutter Color object.
  static Color _parseColor(String? hexColor, Color fallback) {
    if (hexColor == null || hexColor.isEmpty) return fallback;
    hexColor = hexColor.toUpperCase().replaceAll("#", "");
    if (hexColor.length == 6) {
      hexColor = "FF$hexColor";
    }
    return Color(int.tryParse(hexColor, radix: 16) ?? fallback.value);
  }

  static double _parseDouble(String? val, double fallback) {
    if (val == null || val.isEmpty) return fallback;
    // Strip "px" if present
    String cleanVal = val.toLowerCase().replaceAll('px', '').trim();
    return double.tryParse(cleanVal) ?? fallback;
  }

  // Parse CSS-like box shadow into Flutter BoxShadow
  List<BoxShadow> get cardShadow {
    if (cardShadowStr == 'none' || cardShadowStr.isEmpty) {
      return [];
    }
    // Simple basic parsing. Real parsing could get complex.
    // E.g., '0px 4px 6px rgba(0, 0, 0, 0.1)' -> simplified to a fallback if complex
    // If it has "rgba" or "black", we try to approximate
    return [
      BoxShadow(
        color: Color(0x1A000000), // Default generic subtle shadow
        blurRadius: 16,
        offset: Offset(0, 4),
        spreadRadius: 0,
      ),
    ];
  }

  factory TenantBranding.fromJson(Map<String, dynamic> json) {
    final tenantId = (json['tenant_id'] ?? json['id'] ?? '').toString().trim();
    final tenantSlug = (json['tenant_slug'] ?? json['slug'] ?? tenantId)
        .toString()
        .trim();
    final tenantName = (json['tenant_name'] ?? json['appName'] ?? 'MicroFin')
        .toString()
        .trim();

    return TenantBranding(
      id: tenantId.isNotEmpty ? tenantId : 'default',
      slug: tenantSlug.isNotEmpty ? tenantSlug : 'default',
      appName: tenantName.isNotEmpty ? tenantName : 'MicroFin',
      logoPath: json['logo_path'] ?? '',
      fontFamily: json['font_family'] ?? 'Inter',
      themePrimaryColor: _parseColor(
        json['theme_primary_color'] ?? json['primary_color'],
        Color(0xFF1D4ED8),
      ),
      themeSecondaryColor: _parseColor(
        json['theme_secondary_color'] ?? json['secondary_color'],
        Color(0xFF1E40AF),
      ),
      themeTextMain: _parseColor(json['theme_text_main'], Color(0xFF0F172A)),
      themeTextMuted: _parseColor(json['theme_text_muted'], Color(0xFF64748B)),
      themeBgBody: _parseColor(json['theme_bg_body'], Color(0xFFF8FAFC)),
      themeBgCard: _parseColor(json['theme_bg_card'], Color(0xFFFFFFFF)),
      themeBorderColor: _parseColor(
        json['theme_border_color'],
        Color(0xFFE2E8F0),
      ),
      cardBorderWidth: _parseDouble(json['card_border_width']?.toString(), 0.0),
      cardShadowStr: json['card_shadow'] ?? 'none',
    );
  }

  /// Default fallback in case the API fails or no tenants are active
  static TenantBranding defaultTenant = TenantBranding(
    id: 'default',
    slug: 'default',
    appName: 'MicroFin',
    logoPath: '',
    fontFamily: 'Inter',
    themePrimaryColor: Color(0xFF1D4ED8),
    themeSecondaryColor: Color(0xFF1E40AF),
    themeTextMain: Color(0xFF0F172A),
    themeTextMuted: Color(0xFF64748B),
    themeBgBody: Color(0xFFF8FAFC),
    themeBgCard: Color(0xFFFFFFFF),
    themeBorderColor: Color(0xFFE2E8F0),
    cardBorderWidth: 0.0,
    cardShadowStr: 'none',
  );

  /// Fetch dynamic tenants from API
  static Future<void> loadTenants({String? tenantFilter}) async {
    lastLoadSucceeded = false;
    lastLoadError = null;
    tenants = [];

    try {
      final normalizedFilter = tenantFilter?.trim() ?? '';
      final baseUri = Uri.parse(ApiConfig.getUrl('api_active_tenants.php'));
      final url = normalizedFilter.isEmpty
          ? baseUri
          : baseUri.replace(
              queryParameters: {
                ...baseUri.queryParameters,
                'tenant': normalizedFilter,
              },
            );
      final response = await http.get(url).timeout(Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['success'] == true) {
          final List dynamicList =
              (data['tenants'] ?? data['data'] ?? const []) as List;
          tenants = dynamicList
              .whereType<Map<String, dynamic>>()
              .where((tenant) {
                final status = (tenant['status'] ?? '')
                    .toString()
                    .trim()
                    .toLowerCase();
                return status.isEmpty || status == 'active';
              })
              .map(TenantBranding.fromJson)
              .toList();

          if (tenants.isEmpty) {
            tenants = [defaultTenant];
          }
          lastLoadSucceeded = true;
          lastLoadError = null;
        } else {
          lastLoadSucceeded = false;
          lastLoadError = (data['message'] ?? 'Unable to load tenant branding.')
              .toString();
          tenants = [defaultTenant];
        }
      } else {
        lastLoadSucceeded = false;
        lastLoadError = 'Tenant server returned ${response.statusCode}.';
        tenants = [defaultTenant];
      }
    } catch (e) {
      debugPrint('Error loading tenants: $e');
      lastLoadSucceeded = false;
      lastLoadError = 'Unable to load tenant information right now.';
      tenants = [defaultTenant];
    }
  }

  static TenantBranding? fromTenantId(String id) {
    final normalizedId = id.trim().toLowerCase();
    try {
      return tenants.firstWhere(
        (t) =>
            t.slug.toLowerCase() == normalizedId ||
            t.id.toLowerCase() == normalizedId,
      );
    } catch (_) {
      return tenants.isNotEmpty ? tenants.first : defaultTenant;
    }
  }
}
