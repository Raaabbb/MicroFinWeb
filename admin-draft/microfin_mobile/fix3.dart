import 'dart:io';

void main() {
  final dir = Directory('lib');
  final files = dir.listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));

  for (var file in files) {
    String content = file.readAsStringSync();
    
    content = content.replaceAll('tenant.primaryColor', 'tenant.themePrimaryColor')
                     .replaceAll('tenant.secondaryColor', 'tenant.themeSecondaryColor')
                     .replaceAll('tenant.bgCard', 'tenant.themeBgCard')
                     .replaceAll('tenant.bgBody', 'tenant.themeBgBody')
                     .replaceAll('tenant.textMain', 'tenant.themeTextMain')
                     .replaceAll('tenant.textMuted', 'tenant.themeTextMuted')
                     .replaceAll('tenant.logo', 'tenant.logoPath')
                     .replaceAll('tenant.emoji', "'🏦'")
                     .replaceAll('tenant.description', "'Your trusted lending partner'")
                     .replaceAll('tenant.tagline', "'Welcome to MicroFin'");
                     
    // also activeTenant property access
    content = content.replaceAll('activeTenant.value.primaryColor', 'activeTenant.value.themePrimaryColor')
                     .replaceAll('activeTenant.value.secondaryColor', 'activeTenant.value.themeSecondaryColor')
                     .replaceAll('activeTenant.value.bgCard', 'activeTenant.value.themeBgCard')
                     .replaceAll('activeTenant.value.bgBody', 'activeTenant.value.themeBgBody')
                     .replaceAll('activeTenant.value.textMain', 'activeTenant.value.themeTextMain')
                     .replaceAll('activeTenant.value.textMuted', 'activeTenant.value.themeTextMuted')
                     .replaceAll('activeTenant.value.logo', 'activeTenant.value.logoPath')
                     .replaceAll('activeTenant.value.emoji', "'🏦'")
                     .replaceAll('activeTenant.value.description', "'Your trusted lending partner'")
                     .replaceAll('activeTenant.value.tagline', "'Welcome to MicroFin'");

    // what if they did `t.primaryColor` in tenant_branding or splash_screen?
    content = content.replaceAll('t.primaryColor', 't.themePrimaryColor')
                     .replaceAll('t.secondaryColor', 't.themeSecondaryColor')
                     .replaceAll('t.emoji', "'🏦'")
                     .replaceAll('t.description', "'Your trusted lending partner'");

    // also some const styling issues?
    // Let's replace const TextSpan with just TextSpan, const WidgetSpan with WidgetSpan
    content = content.replaceAll('const TextSpan(', 'TextSpan(');
    content = content.replaceAll('const WidgetSpan(', 'WidgetSpan(');
    content = content.replaceAll('const AlwaysStoppedAnimation(', 'AlwaysStoppedAnimation(');

    if (content != file.readAsStringSync()) {
      file.writeAsStringSync(content);
    }
  }
}

