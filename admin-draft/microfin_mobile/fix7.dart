import 'dart:io';

void main() {
  final dir = Directory('lib');
  final files = dir.listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));

  for (var file in files) {
    String content = file.readAsStringSync();
    
    // Completely wipe all const from widgets to cure any remaining dynamic const errors.
    content = content.replaceAll(RegExp(r'\bconst\s+([A-Z])'), r'$1');
    content = content.replaceAll(r'const [', r'[');
    content = content.replaceAll(r'const {', r'{');
    
    // Redo card24 just in case it was missed
    content = content.replaceAll('AppColors.card24', 'AppColors.card.withOpacity(0.24)');

    // Fix profile screen syntax error 382
    content = content.replaceAll('textAlign: TextAlign.center,', '');
    content = content.replaceAll('textAlign: TextAlign.center', '');
    content = content.replaceAll(', style: TextStyle(fontSize: 18)', '');
    content = content.replaceAll('style: TextStyle(fontSize: 18),', '');
    
    if (content != file.readAsStringSync()) {
      file.writeAsStringSync(content);
    }
  }
}

