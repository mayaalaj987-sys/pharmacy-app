import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/locked_feature_tile.dart';
import '../../../employee_offers/presentation/cubit/employee_offers_cubit.dart';
import '../../../employee_offers/presentation/cubit/employee_offers_state.dart';
import '../../../employee_offers/presentation/pages/employee_offers_page.dart';
import '../../../employee_workspace/presentation/pages/employee_account_page.dart';
import '../../../notifications/presentation/cubit/notifications_cubit.dart';
import '../../../notifications/presentation/cubit/notifications_state.dart';
import '../../../notifications/presentation/pages/notifications_page.dart';
import '../../../support/presentation/pages/support_page.dart';
import '../../data/models/auth_session_model.dart';
import '../cubit/auth_cubit.dart';

/// What an employee sees before any pharmacy has taken them on.
///
/// Replaces a two-button dead end — Refresh and Logout — that was written from
/// the pharmacist's mental model and offered a job seeker nothing at all.
///
/// The shell is deliberately the same shape as the working one: same bar, same
/// three destinations, so nothing about the app appears to change when they are
/// hired. Medicines and POS are locked rather than absent, so the tabs mean
/// something before they work.
class EmployeeUnattachedShell extends StatefulWidget {
  final AuthSession session;

  const EmployeeUnattachedShell({super.key, required this.session});

  @override
  State<EmployeeUnattachedShell> createState() => _EmployeeUnattachedShellState();
}

class _EmployeeUnattachedShellState extends State<EmployeeUnattachedShell> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    // The employee endpoint, not the pharmacy-scoped one: that is gated on an
    // active pharmacy, so calling it here would 403 on every open.
    context.read<NotificationsCubit>().loadForEmployee();
  }

  @override
  Widget build(BuildContext context) {
    final actor = widget.session.actor;

    return Scaffold(
      backgroundColor: AppColors.veryLightGreen,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(actor.name, style: const TextStyle(fontSize: 16)),
            const Text(
              'Looking for work',
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.normal),
            ),
          ],
        ),
        actions: [
          _bell(),
          IconButton(
            key: const ValueKey('unattached-account-button'),
            tooltip: 'My account',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => EmployeeAccountPage(actor: actor),
              ),
            ),
            icon: const Icon(Icons.manage_accounts),
          ),
          IconButton(
            tooltip: 'Contact support',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const SupportPage()),
            ),
            icon: const Icon(Icons.support_agent),
          ),
          IconButton(
            tooltip: 'Log out',
            onPressed: () => context.read<AuthCubit>().logout(),
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: switch (_index) {
        0 => const EmployeeOffersPage(),
        1 => const LockedFeaturePage(
          icon: Icons.medication,
          title: 'Medicines',
          reason:
              'The medicine list belongs to a pharmacy. Accept an offer and it '
              'opens here.',
        ),
        _ => const LockedFeaturePage(
          icon: Icons.point_of_sale,
          title: 'Point of sale',
          reason:
              'Selling happens on behalf of a pharmacy. Accept an offer and '
              'this opens here.',
        ),
      },
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _index,
        // The locked items stay tappable and do switch tabs. An item that does
        // nothing when pressed reads as a broken app; one that explains itself
        // reads as a closed door.
        onTap: (value) => setState(() => _index = value),
        type: BottomNavigationBarType.fixed,
        items: [
          BottomNavigationBarItem(
            icon: _offersIcon(),
            label: 'Offers',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.medication),
            label: 'Medicines',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.point_of_sale),
            label: 'POS',
          ),
        ],
      ),
    );
  }

  Widget _offersIcon() {
    return BlocBuilder<EmployeeOffersCubit, EmployeeOffersState>(
      builder: (context, state) {
        final waiting = state.actionable;
        return Stack(
          clipBehavior: Clip.none,
          children: [
            const Icon(Icons.work_outline),
            if (waiting > 0)
              Positioned(
                right: -6,
                top: -4,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                  decoration: const BoxDecoration(
                    color: AppColors.errorRed,
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '$waiting',
                    style: const TextStyle(fontSize: 9, color: Colors.white),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }

  Widget _bell() {
    return BlocBuilder<NotificationsCubit, NotificationsState>(
      builder: (context, notifications) {
        final unread = notifications.unreadCount;
        return IconButton(
          key: const ValueKey('unattached-bell-button'),
          tooltip: 'Notifications',
          onPressed: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const NotificationsPage()),
          ),
          icon: Stack(
            clipBehavior: Clip.none,
            children: [
              const Icon(Icons.notifications),
              if (unread > 0)
                Positioned(
                  right: -4,
                  top: -4,
                  child: Container(
                    padding: const EdgeInsets.all(3),
                    decoration: const BoxDecoration(
                      color: AppColors.errorRed,
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      '$unread',
                      style: const TextStyle(fontSize: 8, color: Colors.white),
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}
