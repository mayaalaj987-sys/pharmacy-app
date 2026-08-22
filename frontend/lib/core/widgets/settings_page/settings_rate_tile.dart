import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../../features/rating/data/rating_repository.dart';
import '../../../features/rating/presentation/cubit/rating_cubit.dart';
import '../../../features/rating/presentation/cubit/rating_state.dart';
import '../../network/user_facing_error.dart';
import '../../theme/app_colors.dart';

/// Rate the application: stars, and room to say why.
///
/// Backed by `GET /rating` and `POST /rating`. The pharmacist id comes from the
/// authenticated session; the backend rejects any mismatch.
///
/// Re-openable. It used to lock after the first submission, which held somebody
/// to one bad afternoon forever and — once the note existed — put it out of
/// reach of everyone who had already left a star.
class SettingsRateTile extends StatefulWidget {
  const SettingsRateTile({super.key});

  @override
  State<SettingsRateTile> createState() => _SettingsRateTileState();
}

class _SettingsRateTileState extends State<SettingsRateTile> {
  @override
  void initState() {
    super.initState();
    context.read<RatingCubit>().load();
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<RatingCubit, RatingState>(
      builder: (context, state) {
        final rating = state.rating;

        return Card(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          child: ListTile(
            leading: const Icon(Icons.star, color: AppColors.pendingOrange),
            title: const Text(
              "Rate App",
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            subtitle: state.status == RatingStatus.loading
                ? const Text("Loading...")
                : Text(
                    rating.hasRated
                        ? "You rated ${rating.myStars}/5 — tap to change it"
                        : rating.ratingsCount > 0
                        ? "Average ${rating.averageStars}/5 from ${rating.ratingsCount} ratings"
                        : "Not rated yet",
                  ),
            trailing: rating.hasRated
                ? const Icon(Icons.edit_outlined, color: AppColors.tealGreen)
                : const Icon(
                    Icons.arrow_forward_ios,
                    size: 18,
                    color: AppColors.tealGreen,
                  ),
            onTap: state.submitting ? null : () => _openDialog(context),
          ),
        );
      },
    );
  }

  Future<void> _openDialog(BuildContext context) async {
    final cubit = context.read<RatingCubit>();
    final messenger = ScaffoldMessenger.of(context);
    final pharmacistId = context.read<AuthCubit>().session?.actor.id;

    if (pharmacistId == null) {
      messenger.showSnackBar(
        const SnackBar(content: Text("Sign in to rate the application.")),
      );

      return;
    }

    final result = await showDialog<(int, String)>(
      context: context,
      // Seeded from whatever they said last, so revising is an edit rather
      // than starting over.
      builder: (_) => _RatingDialog(existing: cubit.state.rating),
    );

    if (result == null || !mounted) return;

    final ok = await cubit.submit(
      pharmacistId: pharmacistId,
      stars: result.$1,
      note: result.$2,
    );

    messenger.showSnackBar(
      SnackBar(
        backgroundColor: ok ? null : AppColors.errorRed,
        content: Text(
          ok
              ? 'Thanks — your feedback has been recorded.'
              : userFacingError(
                  cubit.state.error,
                  fallback: 'Unable to submit your rating.',
                ),
        ),
      ),
    );
  }
}

/// The stars and the note.
///
/// A widget rather than a controller built inside a method: awaiting
/// `showDialog` returns when the button is pressed, not when the dialog has
/// finished animating away, so disposing the controller straight afterwards
/// destroys it while the field is still being built for its exit.
class _RatingDialog extends StatefulWidget {
  final AppRating existing;

  const _RatingDialog({required this.existing});

  @override
  State<_RatingDialog> createState() => _RatingDialogState();
}

class _RatingDialogState extends State<_RatingDialog> {
  late final TextEditingController _note;
  late int _stars;

  @override
  void initState() {
    super.initState();
    _stars = widget.existing.myStars ?? 0;
    _note = TextEditingController(text: widget.existing.myNote ?? '');
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(
        widget.existing.hasRated ? "Change your rating" : "Rate Application",
        style: const TextStyle(fontWeight: FontWeight.bold),
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text("Choose your rating"),
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(5, (index) {
              return IconButton(
                key: ValueKey('rating-star-${index + 1}'),
                onPressed: () => setState(() => _stars = index + 1),
                icon: Icon(
                  index < _stars ? Icons.star : Icons.star_border,
                  color: AppColors.pendingOrange,
                  size: 35,
                ),
              );
            }),
          ),
          const SizedBox(height: 10),
          Text(
            "$_stars / 5",
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: AppColors.tealGreen,
            ),
          ),
          const SizedBox(height: 16),
          // The part anyone can act on. A star says somebody was unhappy;
          // this says what to fix.
          TextField(
            key: const ValueKey('rating-note-field'),
            controller: _note,
            maxLines: 3,
            maxLength: 1000,
            textCapitalization: TextCapitalization.sentences,
            decoration: const InputDecoration(
              labelText: 'What worked, what did not (optional)',
              alignLabelWithHint: true,
              border: OutlineInputBorder(),
            ),
          ),
        ],
      ),
      actionsPadding: const EdgeInsets.only(left: 16, right: 20, bottom: 24),
      actions: [
        Row(
          children: [
            Expanded(
              child: TextButton(
                style: TextButton.styleFrom(
                  foregroundColor: AppColors.tealGreen,
                  backgroundColor: AppColors.veryLightGreen,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  minimumSize: const Size(0, 36),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(20),
                  ),
                ),
                onPressed: () => Navigator.pop(context),
                child: const Text("Cancel", style: TextStyle(fontSize: 13)),
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: ElevatedButton(
                key: const ValueKey('rating-submit-button'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.tealGreen,
                  foregroundColor: AppColors.white,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  minimumSize: const Size(0, 36),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(20),
                  ),
                  elevation: 0,
                ),
                onPressed: _stars == 0
                    ? null
                    : () => Navigator.pop(context, (_stars, _note.text)),
                child: Text(
                  widget.existing.hasRated ? "Update" : "Submit",
                  style: const TextStyle(fontSize: 13),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}
