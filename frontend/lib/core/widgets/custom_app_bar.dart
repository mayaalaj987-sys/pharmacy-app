import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../features/notifications/presentation/cubit/notifications_cubit.dart';
import '../../features/notifications/presentation/cubit/notifications_state.dart';
import '../../features/notifications/presentation/pages/notifications_page.dart';
import '../theme/app_colors.dart';

class CustomAppBar extends StatelessWidget {
  final String title;

  /// Hidden on the notifications screen itself.
  final bool showNotificationBell;

  const CustomAppBar({
    super.key,
    required this.title,
    this.showNotificationBell = true,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: AppColors.darkGreen,
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(20)),
      ),
      child: SafeArea(
        child: SizedBox(
          height: 60,
          child: Row(
            children: [
              //const CircleAvatar(
              // backgroundColor: AppColors.white,
              // child: Icon(Icons.medication, color: AppColors.darkGreen),
              //),
              const SizedBox(width: 1),

              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    color: AppColors.white,
                    fontSize: 25,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),

              if (showNotificationBell)
                BlocBuilder<NotificationsCubit, NotificationsState>(
                  builder: (context, state) {
                    final unread = state.unreadCount;
                    return InkWell(
                      key: const ValueKey('notification-bell'),
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const NotificationsPage(),
                        ),
                      ),
                      child: Stack(
                        clipBehavior: Clip.none,
                        children: [
                          const Padding(
                            padding: EdgeInsets.all(4),
                            child: Icon(
                              Icons.notifications,
                              color: AppColors.white,
                            ),
                          ),
                          if (unread > 0)
                            Positioned(
                              right: 0,
                              top: 0,
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 5,
                                  vertical: 2,
                                ),
                                decoration: const BoxDecoration(
                                  color: AppColors.errorRed,
                                  shape: BoxShape.rectangle,
                                  borderRadius: BorderRadius.all(
                                    Radius.circular(10),
                                  ),
                                ),
                                child: Text(
                                  state.badgeLabel,
                                  style: const TextStyle(
                                    color: AppColors.white,
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
                    );
                  },
                ),
            ],
          ),
        ),
      ),
    );
  }
}
