import 'dart:io';

void main() {
  final dir = Directory('lib');
  final files = dir.listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));

  for (var file in files) {
    String content = file.readAsStringSync();
    String newContent = content
        .replaceAll('activeTenant.value.primaryColor', 'activeTenant.value.themePrimaryColor')
        .replaceAll('activeTenant.value.secondaryColor', 'activeTenant.value.themeSecondaryColor')
        .replaceAll('AppColors.successLight', 'AppColors.bg')
        .replaceAll('AppColors.successText', 'AppColors.primary')
        .replaceAll('AppColors.success', 'AppColors.primary')
        .replaceAll('AppColors.warningLight', 'AppColors.bg')
        .replaceAll('AppColors.warningText', 'AppColors.secondary')
        .replaceAll('AppColors.warning', 'AppColors.secondary')
        .replaceAll('AppColors.dangerLight', 'AppColors.bg')
        .replaceAll('AppColors.dangerText', 'AppColors.secondary')
        .replaceAll('AppColors.danger', 'AppColors.secondary')
        .replaceAll('AppColors.infoLight', 'AppColors.bg')
        .replaceAll('AppColors.infoText', 'AppColors.primary')
        .replaceAll('AppColors.info', 'AppColors.primary')
        .replaceAll('AppColors.indigoLight', 'AppColors.bg')
        .replaceAll('AppColors.indigo', 'AppColors.primary')
        .replaceAll('AppColors.separatorStrong', 'AppColors.border')
        .replaceAll('AppColors.separator', 'AppColors.border')
        .replaceAll('AppColors.surfaceVariant', 'AppColors.card')
        .replaceAll('AppColors.surfaceMuted', 'AppColors.bg')
        .replaceAll('Colors.white70', 'AppColors.textMuted')
        .replaceAll('Colors.white', 'AppColors.card')
        .replaceAll('Colors.black54', 'AppColors.textMuted')
        .replaceAll('Colors.black12', 'AppColors.border')
        .replaceAll('Colors.black', 'AppColors.textMain')
        .replaceAll(RegExp(r'Colors\.grey\[[\d]+\]'), 'AppColors.textMuted')
        .replaceAll('Colors.grey', 'AppColors.textMuted')
        .replaceAll(RegExp(r'Colors\.red\[[\d]+\]'), 'AppColors.secondary')
        .replaceAll('Colors.red', 'AppColors.secondary')
        .replaceAll(RegExp(r'Colors\.green\[[\d]+\]'), 'AppColors.primary')
        .replaceAll('Colors.green', 'AppColors.primary')
        .replaceAll(RegExp(r'Colors\.blue\[[\d]+\]'), 'AppColors.primary')
        .replaceAll('Colors.blue', 'AppColors.primary')
        .replaceAll('AppColors.textLight', 'AppColors.textMuted')
        .replaceAll('AppColors.textBody', 'AppColors.textMuted')
        // Now for shadows
        .replaceAll(RegExp(r'AppColors\.elevatedShadow\(.*?\)'), 'AppColors.cardShadow')
        // bgCard handling if there was any left natively
        .replaceAll('activeTenant.value.bgBody', 'activeTenant.value.themeBgBody')
        .replaceAll('activeTenant.value.bgCard', 'activeTenant.value.themeBgCard')
        .replaceAll('activeTenant.value.textMain', 'activeTenant.value.themeTextMain')
        .replaceAll('activeTenant.value.textMuted', 'activeTenant.value.themeTextMuted')
        .replaceAll('activeTenant.value.logo', 'activeTenant.value.logoPath')
        // other old AppColors functions
        .replaceAll('AppColors.cardShadowMd', 'AppColors.cardShadow');

    if (content != newContent) {
      file.writeAsStringSync(newContent);
    }
  }
}

