/// How a pharmacy or a person was rated by the other side, in aggregate.
///
/// Never individual verdicts. A pharmacy has two staff, so attributing an
/// honest low rating would make it unsurvivable for whoever gave it.
class PartyRating {
  /// Null when nobody has rated yet — which is not the same as zero stars, and
  /// must not be shown as one.
  final double? average;

  final int count;

  const PartyRating({this.average, this.count = 0});

  bool get hasAny => average != null && count > 0;

  /// "4.5 (3)" — the number and how much weight it carries. An average from a
  /// single job means much less than one from several.
  String get label =>
      hasAny ? '${average!.toStringAsFixed(1)} ($count)' : 'Not rated yet';

  factory PartyRating.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const PartyRating();
    final raw = json['average'];
    final rawCount = json['count'];

    return PartyRating(
      average: raw is num ? raw.toDouble() : double.tryParse(raw?.toString() ?? ''),
      count: rawCount is num ? rawCount.toInt() : 0,
    );
  }
}
