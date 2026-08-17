import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../features/auth/presentation/cubit/auth_cubit.dart';
import '../../../features/rating/presentation/cubit/rating_cubit.dart';
import '../../../features/rating/presentation/cubit/rating_state.dart';
import '../../network/user_facing_error.dart';
import '../../theme/app_colors.dart';

/// Rate the application (1-5 stars, once per pharmacist).
///
/// Backed by `GET /rating` and `POST /rating`. The pharmacist id is taken from
/// the authenticated session; the backend rejects any mismatch and returns 400
/// when a rating already exists, which is surfaced honestly rather than faked.
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
          color: AppColors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
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
                        ? "You rated ${rating.myStars}/5"
                        : rating.ratingsCount > 0
                        ? "Average ${rating.averageStars}/5 from ${rating.ratingsCount} ratings"
                        : "Not rated yet",
                  ),
            trailing: rating.hasRated
                ? const Icon(Icons.check_circle, color: AppColors.lightGreen)
                : const Icon(
                    Icons.arrow_forward_ios,
                    size: 18,
                    color: AppColors.tealGreen,
                  ),
            onTap: rating.hasRated || state.submitting
                ? null
                : () => _openDialog(context),
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

    var selectedRating = 0;

    final stars = await showDialog<int>(
      context: context,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              backgroundColor: AppColors.white,
              title: const Text(
                "Rate Application",
                style: TextStyle(fontWeight: FontWeight.bold),
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
                        onPressed: () =>
                            setDialogState(() => selectedRating = index + 1),
                        icon: Icon(
                          index < selectedRating
                              ? Icons.star
                              : Icons.star_border,
                          color: AppColors.pendingOrange,
                          size: 35,
                        ),
                      );
                    }),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    "$selectedRating / 5",
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: AppColors.tealGreen,
                    ),
                  ),
                ],
              ),
              actionsPadding: const EdgeInsets.only(
                left: 16,
                right: 20,
                bottom: 24,
              ),
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
                        onPressed: () => Navigator.pop(dialogContext),
                        child: const Text(
                          "Cancel",
                          style: TextStyle(fontSize: 13),
                        ),
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
                        onPressed: selectedRating == 0
                            ? null
                            : () => Navigator.pop(dialogContext, selectedRating),
                        child: const Text(
                          "Submit",
                          style: TextStyle(fontSize: 13),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            );
          },
        );
      },
    );

    if (stars == null) return;

    final ok = await cubit.submit(pharmacistId: pharmacistId, stars: stars);

    messenger.showSnackBar(
      SnackBar(
        backgroundColor: ok ? AppColors.tealGreen : AppColors.errorRed,
        content: Text(
          ok
              ? "Thanks for rating $stars stars"
              : userFacingError(
                  cubit.state.error,
                  fallback: 'Unable to submit your rating.',
                ),
        ),
      ),
    );
  }
}
