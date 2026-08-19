import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../core/format/money.dart';
import '../../../../core/theme/app_colors.dart';

/// The pieces the analytics screen is built from.
///
/// Kept together because they only make sense next to each other: the same
/// palette runs through the cards, the bars and the donut, and a figure that
/// changed colour between two of them would read as a different figure.
const analyticsPalette = <Color>[
  AppColors.darkGreen,
  Color(0xFF3B82F6),
  Color(0xFFF59E0B),
  Color(0xFFEF4444),
  Color(0xFF8B5CF6),
  Color(0xFF14B8A6),
  Color(0xFFEC4899),
  Color(0xFF64748B),
];

/// One headline number with its own icon.
///
/// [footnote] is where the qualification goes — the share of inventory that
/// has expired, the fact that a negative balance means more was bought than
/// sold. A number without it invites the wrong conclusion.
class StatCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color colour;
  final String? footnote;
  final bool alarming;

  const StatCard({
    super.key,
    required this.title,
    required this.value,
    required this.icon,
    required this.colour,
    this.footnote,
    this.alarming = false,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: alarming
              ? AppColors.errorRed.withValues(alpha: .4)
              : Colors.black.withValues(alpha: .06),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: colour,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, size: 16, color: Colors.white),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(fontSize: 11, color: Colors.black54),
                  textAlign: TextAlign.end,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value,
              style: TextStyle(
                fontSize: 19,
                fontWeight: FontWeight.bold,
                color: alarming ? AppColors.errorRed : null,
              ),
            ),
          ),
          if (footnote != null) ...[
            const SizedBox(height: 2),
            Text(
              footnote!,
              style: TextStyle(
                fontSize: 10,
                color: alarming ? AppColors.errorRed : Colors.black45,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// A titled box the charts and lists sit in.
class AnalyticsSection extends StatelessWidget {
  final String title;
  final String? subtitle;
  final Widget child;

  const AnalyticsSection({
    super.key,
    required this.title,
    required this.child,
    this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.black.withValues(alpha: .06)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 2),
            Text(
              subtitle!,
              style: const TextStyle(fontSize: 11, color: Colors.black54),
            ),
          ],
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

/// A ranked list drawn as bars, longest first.
///
/// The bar is relative to the biggest entry rather than to the total, because
/// the question these lists answer is "what leads", and a share-of-total bar
/// makes everything below the top look identical.
class RankedBars extends StatelessWidget {
  final List<({String label, String value, double weight})> rows;
  final String emptyMessage;

  const RankedBars({super.key, required this.rows, required this.emptyMessage});

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) {
      return Text(
        emptyMessage,
        style: const TextStyle(fontSize: 12, color: Colors.black54),
      );
    }

    final peak = rows.map((row) => row.weight).reduce(math.max);

    return Column(
      children: [
        for (final (index, row) in rows.indexed)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        row.label,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 13),
                      ),
                    ),
                    Text(
                      row.value,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                        color: AppColors.darkGreen,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 5),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: peak > 0 ? row.weight / peak : 0,
                    minHeight: 6,
                    backgroundColor: Colors.black.withValues(alpha: .06),
                    valueColor: AlwaysStoppedAnimation(
                      analyticsPalette[index % analyticsPalette.length],
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

/// Revenue as columns, one per period.
///
/// Drawn by hand rather than pulled in as a charting package: four bars and an
/// axis do not justify a dependency, and this way the currency formatting is
/// the app's own.
class RevenueBars extends StatelessWidget {
  final Map<String, double> byPeriod;
  final String selected;

  const RevenueBars({
    super.key,
    required this.byPeriod,
    required this.selected,
  });

  @override
  Widget build(BuildContext context) {
    final entries = byPeriod.entries.toList();

    if (entries.isEmpty || entries.every((entry) => entry.value == 0)) {
      return const SizedBox(
        height: 60,
        child: Center(
          child: Text(
            'No sales recorded yet.',
            style: TextStyle(fontSize: 12, color: Colors.black54),
          ),
        ),
      );
    }

    final peak = entries.map((entry) => entry.value).reduce(math.max);

    return SizedBox(
      height: 150,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          for (final entry in entries)
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Text(
                    money(entry.value),
                    style: const TextStyle(fontSize: 9),
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Container(
                    margin: const EdgeInsets.symmetric(horizontal: 6),
                    height: peak > 0
                        ? math.max(4, 100 * entry.value / peak)
                        : 4,
                    decoration: BoxDecoration(
                      color: entry.key == selected
                          ? AppColors.darkGreen
                          : AppColors.darkGreen.withValues(alpha: .3),
                      borderRadius: const BorderRadius.vertical(
                        top: Radius.circular(6),
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    entry.key[0].toUpperCase() + entry.key.substring(1),
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: entry.key == selected
                          ? FontWeight.bold
                          : FontWeight.normal,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/// Payment methods as a ring, with the shares the server worked out.
class PaymentDonut extends StatelessWidget {
  final List<({String label, double share, String value})> slices;

  const PaymentDonut({super.key, required this.slices});

  @override
  Widget build(BuildContext context) {
    if (slices.isEmpty) {
      return const Text(
        'Nothing has been sold in this period.',
        style: TextStyle(fontSize: 12, color: Colors.black54),
      );
    }

    return Row(
      children: [
        SizedBox.square(
          dimension: 110,
          child: CustomPaint(painter: _DonutPainter(slices)),
        ),
        const SizedBox(width: 20),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final (index, slice) in slices.indexed)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Row(
                    children: [
                      Container(
                        width: 10,
                        height: 10,
                        decoration: BoxDecoration(
                          color:
                              analyticsPalette[index % analyticsPalette.length],
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          slice.label,
                          style: const TextStyle(fontSize: 12),
                        ),
                      ),
                      Text(
                        '${slice.share.toStringAsFixed(0)}%',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _DonutPainter extends CustomPainter {
  final List<({String label, double share, String value})> slices;

  _DonutPainter(this.slices);

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Rect.fromLTWH(0, 0, size.width, size.height).deflate(14);
    var start = -math.pi / 2;

    for (final (index, slice) in slices.indexed) {
      final sweep = slice.share / 100 * 2 * math.pi;

      canvas.drawArc(
        rect,
        start,
        sweep,
        false,
        Paint()
          ..color = analyticsPalette[index % analyticsPalette.length]
          ..style = PaintingStyle.stroke
          ..strokeWidth = 26,
      );

      start += sweep;
    }
  }

  @override
  bool shouldRepaint(_DonutPainter oldDelegate) => oldDelegate.slices != slices;
}
