import 'dart:io';

void main() {
  final dir = Directory('lib');
  final files = dir.listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));

  for (var file in files) {
    String content = file.readAsStringSync();
    
    // We can't do full AST, but we can do simple regexes to find "const " before widgets that we modified.
    // Example: const Text(..., style: TextStyle(color: AppColors...))
    // A simple hack is to just remove 'const ' when it's right before common widgets that now use dynamic colors
    
    // Replace const SizedBox with regular since we don't care that much about performance right now compared to breaking the app.
    // Wait, SizedBox doesn't usually take colors. We care about const Text, const Icon, const BoxDecoration, const TextStyle
    content = content.replaceAll(RegExp(r'const\s+(Text|Icon|BoxDecoration|TextStyle|BorderSide|Container|Padding|Center|Align|LinearGradient|Row|Column)\('), r'$1(');
    content = content.replaceAll('const [', '['); // for boxShadow arrays
    content = content.replaceAll('const {', '{');
    
    // there could be const EdgeInsets ... let's leave it unless it causes issues.
    
    if (content != file.readAsStringSync()) {
      file.writeAsStringSync(content);
    }
  }
}

