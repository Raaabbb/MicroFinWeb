import 'package:flutter_test/flutter_test.dart';
import 'package:microfin_mobile/main.dart';

void main() {
  testWidgets('App smoke test', (WidgetTester tester) async {
    // Build our app and trigger a frame.
    await tester.pumpWidget(const MicroFinApp());

    // Verify that the app builds successfully.
    expect(find.byType(MicroFinApp), findsOneWidget);
  });
}

