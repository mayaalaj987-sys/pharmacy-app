import 'package:flutter/material.dart';
import 'package:phamacy_managment/features/auth/presentation/pages/signup_page1.dart';
class LogoPage extends StatefulWidget {
  const LogoPage({super.key});

  @override
  State<LogoPage> createState() => _LogoPageState();
}

class _LogoPageState extends State<LogoPage>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  late Animation<double> opacity;
  late Animation<double> scale;
  late Animation<Offset> slide;

  @override
  void initState() {
    super.initState();

    // 🎬 مدة الأنيميشن
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 5000),
    );

    // 🌫 Fade In
    opacity = Tween(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(
        parent: _controller,
        curve: const Interval(0.0, 0.5, curve: Curves.easeOut),
      ),
    );

    // 🔥 Scale pop (Rive-like feel)
    scale = Tween(begin: 0.6, end: 1.0).animate(
      CurvedAnimation(
        parent: _controller,
        curve: Curves.easeOutBack,
      ),
    );

    // ⬆️ حركة طلوع خفيفة
    slide = Tween(begin: const Offset(0, 0.2), end: Offset.zero).animate(
      CurvedAnimation(
        parent: _controller,
        curve: const Interval(0.0, 0.7, curve: Curves.easeOutCubic),
      ),
    );

    // تشغيل الأنيميشن
    _controller.forward();

    // ⏱ الانتقال بعد 5 ثانية
    Future.delayed(const Duration(milliseconds: 5000), () {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => const SignupPage1(),
        ),
      );
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: AnimatedBuilder(
          animation: _controller,
          builder: (context, child) {
            return Opacity(
              opacity: opacity.value,
              child: Transform.translate(
                offset: slide.value * 150,
                child: Transform.scale(
                  scale: scale.value,
                  child: child,
                ),
              ),
            );
          },
          child: Image.asset(
            'assets/images/logo.jpg',
            width: 350,
          ),
        ),
      ),
    );
  }
}

