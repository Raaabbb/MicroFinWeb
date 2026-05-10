import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../main.dart';
import '../theme.dart';
import 'dashboard_screen.dart';
import 'my_loans_screen.dart';
import 'loan_application_screen.dart';
import 'profile_screen.dart';
import 'client_verification_screen.dart';
import '../utils/app_dialogs.dart';

class MainLayout extends StatefulWidget {
  const MainLayout({super.key});

  @override
  State<MainLayout> createState() => _MainLayoutState();
}

class _MainLayoutState extends State<MainLayout>
    with TickerProviderStateMixin, WidgetsBindingObserver {
  int _selectedIndex = 0;
  late AnimationController _navAnimController;

  late final List<Widget> _screens = [
    DashboardScreen(),
    MyLoansScreen(),
    LoanApplicationScreen(),
    ProfileScreen(),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    currentMainTabIndex.value = _selectedIndex;
    _navAnimController = AnimationController(
      duration: Duration(milliseconds: 300),
      vsync: this,
    )..forward();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _navAnimController.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      requestActiveScreenRefresh();
    }
  }

  void _onNavTap(int index) {
    HapticFeedback.selectionClick();

    if (index == 1 || index == 2) {
      final vStatus = currentUser.value?['verification_status'] ?? 'Unverified';
      if (vStatus != 'Approved' && vStatus != 'Verified') {
        AppDialogs.showVerificationRequired(
          context,
          activeTenant.value.themePrimaryColor,
          status: vStatus,
        );
        return;
      }
    }

    setState(() => _selectedIndex = index);
    currentMainTabIndex.value = index;
    requestActiveScreenRefresh();
  }

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: AppColors.bg,
        extendBody: true,
        body: IndexedStack(index: _selectedIndex, children: _screens),
        bottomNavigationBar: _buildBottomNav(primary),
      ),
    );
  }

  Widget _buildBottomNav(Color primary) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(40),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 30,
            offset: const Offset(0, 10),
            spreadRadius: -4,
          ),
          BoxShadow(
            color: Colors.black.withOpacity(0.02),
            blurRadius: 20,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              _NavItem(
                icon: Icons.home_rounded,
                selectedIcon: Icons.home_rounded,
                label: 'HOME',
                index: 0,
                currentIndex: _selectedIndex,
                primary: primary,
                onTap: _onNavTap,
              ),
              _NavItem(
                icon: Icons.payments_outlined,
                selectedIcon: Icons.payments,
                label: 'PAYMENTS',
                index: 1,
                currentIndex: _selectedIndex,
                primary: primary,
                onTap: _onNavTap,
              ),
              _NavItem(
                icon: Icons.account_balance_outlined,
                selectedIcon: Icons.account_balance_rounded,
                label: 'LOANS',
                index: 2,
                currentIndex: _selectedIndex,
                primary: primary,
                onTap: _onNavTap,
              ),
              _NavItem(
                icon: Icons.settings_outlined,
                selectedIcon: Icons.settings_rounded,
                label: 'SETTINGS',
                index: 3,
                currentIndex: _selectedIndex,
                primary: primary,
                onTap: _onNavTap,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final int index;
  final int currentIndex;
  final Color primary;
  final ValueChanged<int> onTap;

  const _NavItem({
    required this.icon,
    required this.selectedIcon,
    required this.label,
    required this.index,
    required this.currentIndex,
    required this.primary,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final bool isSelected = index == currentIndex;

    return GestureDetector(
      onTap: () => onTap(index),
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 200),
              transitionBuilder: (child, animation) =>
                  ScaleTransition(scale: animation, child: child),
              child: isSelected
                  ? Container(
                      key: const ValueKey('selected'),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: primary,
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: primary.withOpacity(0.35),
                            blurRadius: 10,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: Icon(selectedIcon, size: 20, color: Colors.white),
                    )
                  : Padding(
                      key: const ValueKey('unselected'),
                      padding: const EdgeInsets.only(top: 8),
                      child: Icon(
                        icon,
                        size: 24,
                        color: const Color(0xFF9CA3AF),
                      ),
                    ),
            ),
            if (!isSelected) ...[
              const SizedBox(height: 6),
              Text(
                label,
                style: const TextStyle(
                  fontSize: 9,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF9CA3AF),
                  letterSpacing: 0.5,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
