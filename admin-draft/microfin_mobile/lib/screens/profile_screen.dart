import 'package:flutter/material.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../main.dart';
import '../theme.dart';
import '../utils/api_config.dart';
import '../utils/app_dialogs.dart';
import 'support_center_screen.dart';
import 'live_chat_screen.dart';
import 'splash_screen.dart';
import 'manage_profile_screen.dart';
import 'change_password_screen.dart';
import 'credit_standing_screen.dart';


class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _notificationsOn = true;
  bool _twoFaOn = false;
  int _activeLoans = 0;
  int _totalApplications = 0;
  String _memberSince = '';
  String _clientCode = '';
  String _email = '—';
  String _phone = '—';
  String _dob = '—';
  List<dynamic> _documents = [];

  @override
  void initState() {
    super.initState();
    activeScreenRefreshTick.addListener(_handleExternalRefresh);
    _fetchAccountSummary();
  }

  @override
  void dispose() {
    activeScreenRefreshTick.removeListener(_handleExternalRefresh);
    super.dispose();
  }

  void _handleExternalRefresh() {
    if (!mounted || currentMainTabIndex.value != 3) return;
    _fetchAccountSummary();
  }

  Future<void> _fetchAccountSummary() async {
    if (currentUser.value == null) return;
    try {
      final url = Uri.parse(
        ApiConfig.getUrl(
          'api_get_my_loans.php?user_id=${currentUser.value!['user_id']}&tenant_id=${activeTenant.value.id}&t=${DateTime.now().millisecondsSinceEpoch}',
        ),
      );
      final resp = await http.get(url);
      final data = jsonDecode(resp.body);
      if (data['success'] == true) {
        final loans = data['loans'] as List;
        if (mounted) {
          setState(() {
            _activeLoans = loans
                .where((l) => l['loan_status'] == 'Active')
                .length;
          });
        }
      }

      final appUrl = Uri.parse(
        ApiConfig.getUrl(
          'api_get_my_applications.php?user_id=${currentUser.value!['user_id']}&tenant_id=${activeTenant.value.id}&t=${DateTime.now().millisecondsSinceEpoch}',
        ),
      );
      final appResp = await http.get(appUrl);
      final appData = jsonDecode(appResp.body);
      if (appData['success'] == true && mounted) {
        setState(() {
          _totalApplications = (appData['total'] ?? 0) as int;
          _clientCode = appData['client_code'] ?? '';
          _memberSince = appData['member_since'] ?? '';
        });
      }

      final pUrl = Uri.parse(
        ApiConfig.getUrl(
          'api_get_profile.php?user_id=${currentUser.value!['user_id']}&tenant_id=${activeTenant.value.id}&t=${DateTime.now().millisecondsSinceEpoch}',
        ),
      );
      final pResp = await http.get(pUrl);
      final pData = jsonDecode(pResp.body);
      if (pData['success'] == true && mounted) {
        setState(() {
          _memberSince = pData['profile']['member_since'] ?? '';
          _clientCode = pData['profile']['client_code'] ?? '';
          _email = pData['profile']['email'] ?? '—';
          _phone = pData['profile']['phone_number'] ?? '—';
          _dob = pData['profile']['date_of_birth'] ?? '—';
          _documents = pData['profile']['documents'] ?? [];

          if (_email.isEmpty) _email = '—';
          if (_phone.isEmpty) _phone = '—';
          if (_dob.isEmpty) _dob = '—';
        });
      }
    } catch (_) {}
  }

  String get _fullName {
    final u = currentUser.value;
    if (u == null) return 'User';
    final fn = u['first_name'] ?? '';
    final mn = u['middle_name'] ?? '';
    final ln = u['last_name'] ?? '';
    return [
      fn,
      mn.isNotEmpty ? '${mn[0]}.' : '',
      ln,
    ].where((s) => s.isNotEmpty).join(' ');
  }

  String get _firstName {
    final u = currentUser.value;
    if (u == null) return 'User';
    return (u['first_name'] ?? 'User') as String;
  }

  String get _initials {
    final u = currentUser.value;
    if (u == null) return 'U';
    final fn = (u['first_name'] ?? '') as String;
    final ln = (u['last_name'] ?? '') as String;
    return '${fn.isNotEmpty ? fn[0] : ''}${ln.isNotEmpty ? ln[0] : ''}'
        .toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final tenant = activeTenant.value;
    final primary = tenant.themePrimaryColor;
    final secondary = tenant.themeSecondaryColor;

    return Scaffold(
      backgroundColor: const Color(0xFFF9FAFB),
      body: SafeArea(
        bottom: false,
        child: CustomScrollView(
          physics: const BouncingScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(child: _buildHeader(context, tenant, primary)),

            SliverPadding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 160),
              sliver: SliverList(
                delegate: SliverChildListDelegate([
                  // ── Profile card (avatar + name + badges) ─────────────
                  _buildProfileCard(primary, secondary),
                  const SizedBox(height: 20),

                  // Stats row
                  _statsRow(primary),
                  const SizedBox(height: 24),

                  _sectionLabel('Credit & Account', primary),
                  const SizedBox(height: 14),
                  _card([
                    if ((currentUser.value?['verification_status'] ?? 'Unverified') == 'Approved')
                      _navRow(
                        Icons.insights_rounded,
                        'My Credit Standing',
                        primary,
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const CreditStandingScreen(),
                          ),
                        ),
                      )
                    else
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        child: Row(
                          children: [
                            Icon(Icons.lock_outline_rounded, color: Colors.grey[400], size: 22),
                            const SizedBox(width: 16),
                            Text(
                              'Credit Standing (Locked)',
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w500,
                                color: Colors.grey[400],
                              ),
                            ),
                          ],
                        ),
                      ),
                  ], primary),

                  const SizedBox(height: 28),

                  // Settings
                  _sectionLabel('Settings & Security', primary),
                  const SizedBox(height: 14),
                  _card([
                    _navRow(
                      Icons.person_outline_rounded,
                      'Manage Profile',
                      primary,
                      () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const ManageProfileScreen(),
                        ),
                      ),
                    ),
                    _divider(),
                    _switchRow(
                      Icons.notifications_outlined,
                      'Push Notifications',
                      _notificationsOn,
                      (v) => setState(() => _notificationsOn = v),
                      primary,
                    ),
                    _divider(),
                    _navRow(
                      Icons.lock_outline_rounded,
                      'Change Password',
                      primary,
                      () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const ChangePasswordScreen(),
                        ),
                      ),
                    ),
                  ], primary),
                  const SizedBox(height: 28),

                  // Help
                  _sectionLabel('Help & Support', primary),
                  const SizedBox(height: 14),
                  _card([
                    _navRow(
                      Icons.help_outline_rounded,
                      'FAQ & Help Center',
                      primary,
                      () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const SupportCenterScreen(),
                        ),
                      ),
                    ),
                    _divider(),
                    _navRow(
                      Icons.headset_mic_outlined,
                      'Contact Support',
                      primary,
                      () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const SupportCenterScreen(),
                        ),
                      ),
                    ),
                    _divider(),
                    _navRow(
                      Icons.policy_outlined,
                      'Terms & Privacy Policy',
                      primary,
                      () {},
                    ),
                  ], primary),
                  const SizedBox(height: 28),

                  const SizedBox(height: 14),

                  // Logout
                  GestureDetector(
                    onTap: () => _confirmLogout(context, primary),
                    child: Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 18),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(
                          color: const Color(0xFFEF4444).withOpacity(0.2),
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: const Color(0xFFEF4444).withOpacity(0.05),
                            blurRadius: 16,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      child: const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.logout_rounded,
                            color: Color(0xFFEF4444),
                            size: 20,
                          ),
                          SizedBox(width: 10),
                          Text(
                            'Log Out',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              color: Color(0xFFEF4444),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ]),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ── Same flat header as DashboardScreen ─────────────────────────────────
  Widget _buildHeader(BuildContext context, tenant, Color primary) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: Color(0xFF1F2937),
                ),
                child: Center(
                  child: Text(
                    _initials,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    tenant.appName,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0F292B),
                      height: 1.1,
                      letterSpacing: -0.3,
                    ),
                  ),
                  Text(
                    _firstName,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0F292B),
                      height: 1.1,
                      letterSpacing: -0.3,
                    ),
                  ),
                ],
              ),
            ],
          ),
          ValueListenableBuilder<List<dynamic>>(
            valueListenable: globalNotifications,
            builder: (context, notifs, _) => Stack(
              children: [
                IconButton(
                  onPressed: () =>
                      AppDialogs.showNotifications(context, primary),
                  icon: const Icon(
                    Icons.notifications_rounded,
                    color: Color(0xFF0F292B),
                    size: 26,
                  ),
                ),
                if (notifs.isNotEmpty)
                  Positioned(
                    top: 10,
                    right: 12,
                    child: Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: Color(0xFFEF4444),
                        shape: BoxShape.circle,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ── Compact profile card replacing the old gradient hero ────────────────
  Widget _buildProfileCard(Color primary, Color secondary) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(28),
      decoration: BoxDecoration(
        color: AppColors.textMain, // Sleek Midnight/Brand Dark
        borderRadius: BorderRadius.circular(AppPremium.radius),
        boxShadow: [
          BoxShadow(
            color: AppColors.textMain.withOpacity(0.2),
            blurRadius: 30,
            offset: const Offset(0, 12),
          ),
        ],
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.textMain, AppColors.textMain.withOpacity(0.85)],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            right: -30,
            bottom: -30,
            child: Icon(
              Icons.shield_moon_rounded,
              size: 160,
              color: Colors.white.withOpacity(0.04),
            ),
          ),
          Column(
            children: [
              // Avatar with Halo
              Hero(
                tag: 'profile_avatar',
                child: Container(
                  width: 96,
                  height: 96,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white.withOpacity(0.12),
                    border: Border.all(
                      color: Colors.white.withOpacity(0.3),
                      width: 2.5,
                    ),
                    boxShadow: [
                      BoxShadow(color: Colors.white.withOpacity(0.1), blurRadius: 20)
                    ],
                  ),
                  child: Center(
                    child: Text(
                      _initials,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 34,
                        fontWeight: FontWeight.w900,
                        letterSpacing: -1.5,
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 22),
              Text(
                _fullName,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -1,
                ),
              ),
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  _clientCode.isNotEmpty ? _clientCode : 'ACTIVE MEMBER',
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.9),
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.5,
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _profileBadge(Icons.verified_rounded, 'Verified', primary),
                  const SizedBox(width: 12),
                  _profileBadge(Icons.auto_awesome_rounded, 'Elite Status', primary),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _profileBadge(IconData icon, String label, Color primary) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.2),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Colors.white, size: 14),
          const SizedBox(width: 6),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.3,
            ),
          ),
        ],
      ),
    );
  }

  Widget _statsRow(Color primary) {
    final stats = [
      {
        'label': 'Active Loans',
        'value': '$_activeLoans',
        'icon': Icons.account_balance_wallet_rounded,
      },
      {
        'label': 'Applications',
        'value': '$_totalApplications',
        'icon': Icons.analytics_rounded,
      },
      {
        'label': 'Member Since',
        'value': _memberSince.isNotEmpty ? _memberSince.split('-').first : '—',
        'icon': Icons.auto_graph_rounded,
      },
    ];
    return Row(
      children: stats.asMap().entries.map((e) {
        final i = e.key;
        final s = e.value;
        return Expanded(
          child: Container(
            margin: EdgeInsets.only(right: i < stats.length - 1 ? 12 : 0),
            padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 12),
            decoration: AppPremium.cardDecoration(),
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(color: primary.withOpacity(0.08), shape: BoxShape.circle),
                  child: Icon(s['icon'] as IconData, color: primary, size: 18),
                ),
                const SizedBox(height: 12),
                Text(
                  s['value'] as String,
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: AppColors.textMain,
                    letterSpacing: -1,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  (s['label'] as String).toUpperCase(),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 9,
                    color: AppColors.textMuted,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.5,
                  ),
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _sectionLabel(String title, Color primary) {
    return Padding(
      padding: const EdgeInsets.only(left: 4),
      child: Text(
        title.toUpperCase(),
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w900,
          color: primary,
          letterSpacing: 1.2,
        ),
      ),
    );
  }

  Widget _card(List<Widget> children, Color primary) {
    return Container(
      decoration: AppPremium.cardDecoration(),
      child: Column(children: children),
    );
  }

  Widget _divider() => const Divider(
    height: 1,
    indent: 64,
    endIndent: 20,
    color: Color(0xFFF3F4F6),
  );

  Widget _infoRow(IconData icon, String label, String value, Color primary) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: primary, size: 18),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 11,
                    color: AppColors.textMuted,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  value,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textMain,
                  ),
                ),
              ],
            ),
          ),
          Icon(
            Icons.chevron_right_rounded,
            color: const Color(0xFFE5E7EB),
            size: 18,
          ),
        ],
      ),
    );
  }

  Widget _documentRow(
    IconData icon,
    String label,
    String status,
    Color statusColor,
    Color statusBg,
    Color primary,
  ) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: primary, size: 18),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.textMain,
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(
              color: statusBg,
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              status,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: statusColor,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _uploadDocRow(Color primary) {
    return GestureDetector(
      onTap: () {},
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(Icons.add_rounded, color: primary, size: 20),
            ),
            const SizedBox(width: 14),
            Text(
              'Upload a Document',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: primary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _navRow(
    IconData icon,
    String label,
    Color primary,
    VoidCallback onTap,
  ) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: AppColors.bg,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: AppColors.textMain, size: 20),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textMain,
                  letterSpacing: -0.3,
                ),
              ),
            ),
            Icon(
              Icons.chevron_right_rounded,
              color: AppColors.textMuted,
              size: 18,
            ),
          ],
        ),
      ),
    );
  }

  Widget _switchRow(
    IconData icon,
    String label,
    bool value,
    ValueChanged<bool> onChanged,
    Color primary,
  ) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: primary, size: 18),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.textMain,
              ),
            ),
          ),
          Switch.adaptive(
            value: value,
            onChanged: onChanged,
            activeColor: primary,
          ),
        ],
      ),
    );
  }

  Widget _tenantSwitcherRow(BuildContext context, tenant, Color primary) {
    return GestureDetector(
      onTap: () => _showTenantPicker(context, primary),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(Icons.business_rounded, color: primary, size: 18),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Current App / Tenant',
                    style: TextStyle(fontSize: 11, color: AppColors.textMuted),
                  ),
                  Text(
                    tenant.appName,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textMain,
                    ),
                  ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                'Switch',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: primary,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showTenantPicker(BuildContext context, Color primary) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => Container(
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
        ),
        padding: const EdgeInsets.fromLTRB(24, 16, 24, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),
            Text(
              'Switch Tenant',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: AppColors.textMain,
              ),
            ),
            const SizedBox(height: 16),
            ...[
              '💳 Fundline Mobile',
              '🏦 PlaridelMFB',
              '🌿 Sacred Heart Coop',
            ].map(
              (t) => ListTile(
                title: Text(
                  t,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () => Navigator.pop(context),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _confirmLogout(BuildContext context, Color primary) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => Container(
        padding: const EdgeInsets.all(32),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFE5E7EB),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 32),
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: const Color(0xFFEF4444).withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.logout_rounded,
                color: Color(0xFFEF4444),
                size: 32,
              ),
            ),
            const SizedBox(height: 24),
            const Text(
              'Log Out Account',
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w900,
                color: Color(0xFF111827),
                letterSpacing: -0.5,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Are you sure you want to log out? You will need to re-authenticate to access your account.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                color: Color(0xFF6B7280),
                height: 1.5,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 32),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(context),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      side: const BorderSide(color: Color(0xFFE5E7EB)),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    child: const Text(
                      'Cancel',
                      style: TextStyle(
                        color: Color(0xFF111827),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      currentUser.value = null; // Clear session
                      Navigator.pushAndRemoveUntil(
                        context,
                        MaterialPageRoute(builder: (_) => SplashScreen()),
                        (_) => false,
                      );
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFEF4444),
                      foregroundColor: Colors.white,
                      elevation: 0,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    child: const Text(
                      'Yes, Log Out',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
