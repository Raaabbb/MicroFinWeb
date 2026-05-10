import 'dart:io';

void main() {
  final dir = Directory('lib');
  final files = dir.listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));

  for (var file in files) {
    String content = file.readAsStringSync();
    
    // Emojis
    content = content.replaceAll("Text('🏦'", "Icon(Icons.business_rounded, color: AppColors.textMain, ");
    content = content.replaceAll('Text("🏦"', 'Icon(Icons.business_rounded, color: AppColors.textMain, ');
    
    // Some places had `Text('👤', style: TextStyle(fontSize: 42))`
    content = content.replaceAll(RegExp(r"Text\('👤',.*?\)", dotAll: true), "Icon(Icons.person_rounded, size: 26, color: AppColors.textMain)");
    
    // 🚀
    content = content.replaceAll('🚀', '');
    
    // 👋
    content = content.replaceAll('👋', '');

    if (content != file.readAsStringSync()) {
      file.writeAsStringSync(content);
    }
  }
}

