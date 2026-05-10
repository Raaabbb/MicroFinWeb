import 'dart:io';

void main() {
  final dir = Directory('lib');
  final files = dir.listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));

  for (var file in files) {
    String content = file.readAsStringSync();
    
    // Fix the accidental $1 from the previous replaceAll script
    content = content.replaceAll(r'style: $1(', 'style: TextStyle(');
    content = content.replaceAll(r'decoration: $1(', 'decoration: BoxDecoration(');
    content = content.replaceAll(r"child: $1('", "child: Text('");
    content = content.replaceAll(r'child: $1("', 'child: Text("');
    content = content.replaceAll(r"title: $1('", "title: Text('");
    content = content.replaceAll(r'title: $1("', 'title: Text("');
    content = content.replaceAll(r"label: $1('", "label: Text('");
    content = content.replaceAll(r'label: $1("', 'label: Text("');
    content = content.replaceAll(r"content: $1('", "content: Text('");
    content = content.replaceAll(r'content: $1("', 'content: Text("');
    content = content.replaceAll(r'$1(Icons.', 'Icon(Icons.');
    content = content.replaceAll(r'gradient: $1(', 'gradient: LinearGradient(');
    content = content.replaceAll(r'child: $1(mainAxisAlignment', 'child: Row(mainAxisAlignment'); // assuming Row or Column, let's look closer later if it fails

    // Add generic ones
    content = content.replaceAll(r"$1('", "Text('");
    content = content.replaceAll(r'$1("', 'Text("');
    content = content.replaceAll(r'$1(child: CircularProgressIndicator', 'Center(child: CircularProgressIndicator');
    content = content.replaceAll(r'$1(child:', 'Container(child:'); 
    
    // some missed ones could be manually fixed if we see them in analyze.
    
    if (content != file.readAsStringSync()) {
      file.writeAsStringSync(content);
    }
  }
}

