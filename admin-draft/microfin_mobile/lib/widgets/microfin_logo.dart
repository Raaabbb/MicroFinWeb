import 'package:flutter/material.dart';

class MicroFinLogo extends StatelessWidget {
  final double size;
  final bool elevated;

  const MicroFinLogo({
    super.key,
    this.size = 100,
    this.elevated = true,
  });

  @override
  Widget build(BuildContext context) {
    final shadow = elevated
        ? [
            BoxShadow(
              color: Colors.black.withOpacity(0.16),
              blurRadius: size * 0.18,
              offset: Offset(0, size * 0.07),
            ),
          ]
        : const <BoxShadow>[];

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(size * 0.24),
        boxShadow: shadow,
      ),
      clipBehavior: Clip.antiAlias,
      child: Image.asset(
        'images/microfin_app_icon.png',
        fit: BoxFit.contain,
      ),
    );
  }
}
