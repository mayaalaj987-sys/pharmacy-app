import 'package:flutter/material.dart';

/// A control that is visible but cannot be used yet.
///
/// Generalises the pattern already in `ContinueButtonWidget`: dim to 0.4 and
/// null the callback. Three things are added, because opacity alone is not
/// enough — it reads as "loading" just as easily as "locked":
///
///  * a padlock, so the state is unambiguous at a glance;
///  * a semantics label, since colour and opacity say nothing to a screen
///    reader;
///  * an explanation on tap, because a control that does nothing when pressed
///    reads as a broken app rather than a closed door.
class LockedFeatureTile extends StatelessWidget {
  final IconData icon;
  final String title;

  /// Why it is locked, in the second person. Shown on tap.
  final String reason;

  const LockedFeatureTile({
    super.key,
    required this.icon,
    required this.title,
    required this.reason,
  });

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: '$title, unavailable. $reason',
      button: true,
      enabled: false,
      child: AnimatedOpacity(
        duration: const Duration(milliseconds: 200),
        opacity: 0.4,
        child: Card(
          margin: const EdgeInsets.only(bottom: 10),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          child: ListTile(
            leading: Icon(icon),
            title: Text(title),
            trailing: const Icon(Icons.lock_outline, size: 18),
            onTap: () => ScaffoldMessenger.of(
              context,
            ).showSnackBar(SnackBar(content: Text(reason))),
          ),
        ),
      ),
    );
  }
}

/// The body shown when a whole tab is not available yet.
class LockedFeaturePage extends StatelessWidget {
  final IconData icon;
  final String title;
  final String reason;

  const LockedFeaturePage({
    super.key,
    required this.icon,
    required this.title,
    required this.reason,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Opacity(opacity: 0.4, child: Icon(icon, size: 64)),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.lock_outline, size: 18),
                const SizedBox(width: 6),
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(reason, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}
