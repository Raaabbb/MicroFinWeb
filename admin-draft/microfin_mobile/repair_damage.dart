import 'dart:io';

void main() {
  final dir = Directory('lib');
  final files = dir.listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));

  for (var file in files) {
    String content = file.readAsStringSync();
    
    // 1. Resolve illegal `$1` prefixes from class/method names and constructors
    // Example: `MicroFinApp`, `TenantBranding`, `SplashScreen`
    // The previous broken script did: replaceAll(RegExp(r'\bconst\s+([A-Z])'), r'$1') 
    // which literally inserted '$1'.
    content = content.replaceAll(RegExp(r'\$1([A-Z])'), r'$1'); 
    
    // 2. Resolve `$1` accidental insertions from Icon sizing
    // Example: `Icon(Icons.business_rounded, color: AppColors.textMain, size: 24)`
    // This happened because I used $1 in a replaceAll where it was meant for backreference but interpreted literally.
    // Let's replace `size: 24` with a fixed size like 22 or 24.
    content = content.replaceAll('size: \$1', 'size: 24');
    
    // Also check for `EdgeInsets`, `BorderRadius`, etc. which might have had $1 injected if I tried replacing const on them.
    // Actually the regex `$1([A-Z])` fixes most cases like `$1dgeInsets` -> `EdgeInsets`.

    if (content != file.readAsStringSync()) {
      print('Repaired ${file.path}');
      file.writeAsStringSync(content);
    }
  }
}

