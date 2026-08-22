import 'package:flutter/material.dart';

import '../../../core/layout/responsive_layout.dart';

class OnboardingPage extends StatefulWidget {
  const OnboardingPage({super.key, required this.onFinished});

  final Future<void> Function() onFinished;

  @override
  State<OnboardingPage> createState() => _OnboardingPageState();
}

class _OnboardingPageState extends State<OnboardingPage> {
  final _controller = PageController();
  int _index = 0;
  bool _finishing = false;

  static const _pages = [
    _OnboardingItem(
      icon: Icons.inventory_2_rounded,
      title: 'Your pharmacy, clearly organized',
      description:
          'Track stock, expiry dates, sales, and daily work from one simple dashboard.',
    ),
    _OnboardingItem(
      icon: Icons.medication_liquid_rounded,
      title: 'Find medicines in seconds',
      description:
          'Browse supplier catalogs, search by medicine name, and prepare purchase orders faster.',
    ),
    _OnboardingItem(
      icon: Icons.verified_user_rounded,
      title: 'A secure workflow for your team',
      description:
          'Manage employees, approvals, notifications, and support without losing important details.',
    ),
  ];

  Future<void> _finish() async {
    if (_finishing) return;
    setState(() => _finishing = true);
    await widget.onFinished();
  }

  Future<void> _next() async {
    if (_index == _pages.length - 1) {
      await _finish();
      return;
    }
    await _controller.nextPage(
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              scheme.surface,
              scheme.primaryContainer.withValues(alpha: .55),
            ],
          ),
        ),
        child: ResponsiveContent(
          maxWidth: 760,
          child: Column(
            children: [
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: TextButton(
                  onPressed: _finishing ? null : _finish,
                  child: const Text('Skip'),
                ),
              ),
              Expanded(
                child: PageView.builder(
                  controller: _controller,
                  itemCount: _pages.length,
                  onPageChanged: (value) => setState(() => _index = value),
                  itemBuilder: (context, index) =>
                      _OnboardingSlide(item: _pages[index]),
                ),
              ),
              Row(
                children: [
                  Expanded(
                    child: Row(
                      children: List.generate(
                        _pages.length,
                        (index) => AnimatedContainer(
                          duration: const Duration(milliseconds: 220),
                          curve: Curves.easeOut,
                          width: index == _index ? 28 : 8,
                          height: 8,
                          margin: const EdgeInsetsDirectional.only(end: 8),
                          decoration: BoxDecoration(
                            color: index == _index
                                ? scheme.primary
                                : scheme.outlineVariant,
                            borderRadius: BorderRadius.circular(99),
                          ),
                        ),
                      ),
                    ),
                  ),
                  FilledButton.icon(
                    onPressed: _finishing ? null : _next,
                    icon: _finishing
                        ? SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: scheme.onPrimary,
                            ),
                          )
                        : Icon(
                            _index == _pages.length - 1
                                ? Icons.check_rounded
                                : Icons.arrow_forward_rounded,
                          ),
                    label: Text(
                      _index == _pages.length - 1 ? 'Get started' : 'Next',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }
}

class _OnboardingSlide extends StatelessWidget {
  const _OnboardingSlide({required this.item});

  final _OnboardingItem item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return LayoutBuilder(
      builder: (context, constraints) {
        final isShort = constraints.maxHeight < 520;
        final illustrationSize = (constraints.maxHeight * (isShort ? .44 : .58))
            .clamp(150.0, 360.0);
        final sectionGap = isShort ? 20.0 : 38.0;
        final textGap = isShort ? 8.0 : 14.0;
        return Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: illustrationSize,
              height: illustrationSize * .72,
              decoration: BoxDecoration(
                color: scheme.primaryContainer,
                borderRadius: BorderRadius.circular(36),
                boxShadow: [
                  BoxShadow(
                    color: scheme.primary.withValues(alpha: .12),
                    blurRadius: 36,
                    offset: const Offset(0, 18),
                  ),
                ],
              ),
              alignment: Alignment.center,
              child: Icon(
                item.icon,
                size: illustrationSize * .3,
                color: scheme.onPrimaryContainer,
              ),
            ),
            SizedBox(height: sectionGap),
            Text(
              item.title,
              textAlign: TextAlign.center,
              style: isShort
                  ? theme.textTheme.headlineSmall
                  : theme.textTheme.headlineMedium,
            ),
            SizedBox(height: textGap),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 540),
              child: Text(
                item.description,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyLarge?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _OnboardingItem {
  const _OnboardingItem({
    required this.icon,
    required this.title,
    required this.description,
  });

  final IconData icon;
  final String title;
  final String description;
}
