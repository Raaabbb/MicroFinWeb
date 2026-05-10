import 'dart:io';

void main() {
  final dir = Directory('lib');
  final files = dir.listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));

  for (var file in files) {
    String content = file.readAsStringSync();
    
    // Fix AppColors missing fields
    content = content.replaceAll('AppColors.primaryAccent', 'AppColors.primary')
                     .replaceAll('AppColors.secondaryAccent', 'AppColors.secondary')
                     .replaceAll('AppColors.card24', 'AppColors.card.withOpacity(0.24)');
                     
    // Fix Icon syntax errors
    // Icon(Icons.business_rounded, color: AppColors.textMain, , style: TextStyle(fontSize: 22)) -> Icon(Icons.business_rounded, color: AppColors.textMain, size: 22)
    content = content.replaceAll(RegExp(r"Icon\(Icons\.business_rounded, color: AppColors\.textMain, , style: TextStyle\(fontSize: (\d+)\)(, textAlign: TextAlign\.center)?\)"),
                                 r"Icon(Icons.business_rounded, color: AppColors.textMain, size: 24)");

    content = content.replaceAll(r"Icon(Icons.business_rounded, color: AppColors.textMain, , style: TextStyle(fontSize: 18), textAlign: TextAlign.center)",
                                 r"Icon(Icons.business_rounded, color: AppColors.textMain, size: 18)");

    content = content.replaceAll(r"Icon(Icons.business_rounded, color: AppColors.textMain, , style: TextStyle(fontSize: 40))",
                                 r"Icon(Icons.business_rounded, color: AppColors.textMain, size: 40)");

    // Fix other bad const things
    content = content.replaceAll(r"const WidgetSpan", r"WidgetSpan");
    content = content.replaceAll(r"const TextSpan", r"TextSpan");
    content = content.replaceAll(r"const EdgeInsets", r"EdgeInsets");
    content = content.replaceAll(r"const AlwaysStoppedAnimation", r"AlwaysStoppedAnimation");
    content = content.replaceAll(r"const BorderRadius", r"BorderRadius");
    content = content.replaceAll(r"const Radius", r"Radius");
    content = content.replaceAll(r"const BoxShadow", r"BoxShadow");
    content = content.replaceAll(r"const BorderSide", r"BorderSide");
    content = content.replaceAll(r"const Duration", r"Duration");
    
    // Some places we had const Spacer() and stuff, that's fine.
    
    if (content != file.readAsStringSync()) {
      file.writeAsStringSync(content);
    }
  }
}

