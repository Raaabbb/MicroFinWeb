import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'models/tenant_branding.dart';
import 'screens/splash_screen.dart';
import 'package:google_fonts/google_fonts.dart';

// Global active tenant — drives all UI theming across the app
final ValueNotifier<TenantBranding> activeTenant = ValueNotifier(
  TenantBranding.defaultTenant,
);

// Global current user — tracks logged in state
final ValueNotifier<Map<String, dynamic>?> currentUser = ValueNotifier(null);

// Global notifications — tracks notification lists for unread indicators and popups
final ValueNotifier<List<dynamic>> globalNotifications = ValueNotifier([]);

// Global main-tab state — lets kept-alive tabs know when they should refresh.
final ValueNotifier<int> currentMainTabIndex = ValueNotifier(0);
final ValueNotifier<int> activeScreenRefreshTick = ValueNotifier(0);

void requestActiveScreenRefresh() {
  activeScreenRefreshTick.value = activeScreenRefreshTick.value + 1;
}

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  SystemChrome.setSystemUIOverlayStyle(
    SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.light,
    ),
  );
  runApp(MicroFinApp());
}

class MicroFinApp extends StatelessWidget {
  const MicroFinApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<TenantBranding>(
      valueListenable: activeTenant,
      builder: (context, tenant, _) {
        return MaterialApp(
          title: tenant.appName,
          debugShowCheckedModeBanner: false,
          theme: _buildTheme(tenant),
          home: SplashScreen(),
        );
      },
    );
  }

  ThemeData _buildTheme(TenantBranding tenant) {
    // Try applying GoogleFonts. If it fails due to unknown font, fallback to standard text theme
    TextTheme buildTextTheme() {
      try {
        return GoogleFonts.getTextTheme(tenant.fontFamily);
      } catch (e) {
        return ThemeData.light().textTheme.apply(fontFamily: tenant.fontFamily);
      }
    }

    final textTheme = buildTextTheme().apply(
      bodyColor: tenant.themeTextMain,
      displayColor: tenant.themeTextMain,
    );

    return ThemeData(
      useMaterial3: true,
      fontFamily: tenant.fontFamily,
      textTheme: textTheme,
      colorScheme: ColorScheme.light(
        primary: tenant.themePrimaryColor,
        secondary: tenant.themeSecondaryColor,
        surface: tenant.themeBgCard,
        onPrimary: tenant.themeBgCard,
        onSecondary: tenant.themeBgCard,
        onSurface: tenant.themeTextMain,
        outline: tenant.themeBorderColor,
      ),
      scaffoldBackgroundColor: tenant.themeBgBody,
      appBarTheme: AppBarTheme(
        backgroundColor: tenant.themeBgCard,
        foregroundColor: tenant.themeTextMain,
        elevation: 0,
        iconTheme: IconThemeData(color: tenant.themeTextMain),
        titleTextStyle: textTheme.titleLarge?.copyWith(
          color: tenant.themeTextMain,
          fontWeight: FontWeight.w700,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: tenant.themePrimaryColor,
          foregroundColor: tenant.themeBgCard,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(999), // 999px for pill buttons
          ),
          padding: EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          textStyle: textTheme.labelLarge?.copyWith(
            fontWeight: FontWeight.w600,
            fontSize: 16,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: tenant.themeBgCard,
        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: tenant.themeBorderColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: tenant.themeBorderColor),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: tenant.themePrimaryColor, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: tenant.themeTextMain),
        ),
        hintStyle: textTheme.bodyMedium?.copyWith(
          color: tenant.themeTextMuted,
          fontSize: 16,
        ),
      ),
    );
  }
}
