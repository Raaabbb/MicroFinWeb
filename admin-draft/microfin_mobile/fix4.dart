import 'dart:io';

void main() {
  final dir = Directory('lib');
  final files = dir.listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));

  for (var file in files) {
    String content = file.readAsStringSync();
    
    // Change Image.asset(tenant.logoPath) to Image.network(tenant.logoPath)
    content = content.replaceAll('Image.asset(', 'Image.network(');
    
    // Check if AppColors is used
    if (content.contains('AppColors.') && !content.contains('theme.dart')) {
      // Need to add import
      // count how many parts to get out of dir to base?
      int upCount = file.path.split(Platform.pathSeparator).length - 2; // -1 for filename, -1 for 'lib'
      String importPath = 'theme.dart';
      if (upCount > 0) {
        importPath = List.filled(upCount, '..').join('/') + '/theme.dart';
      }
      content = "import '$importPath';\n" + content;
    }

    if (content != file.readAsStringSync()) {
      file.writeAsStringSync(content);
    }
  }
}

