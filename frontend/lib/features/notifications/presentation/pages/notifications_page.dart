import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../auth/data/models/auth_session_model.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../auth/presentation/pages/purchases_page.dart';
import '../../../auth/presentation/pages/sale_history_page.dart';
import '../../../inventory/presentation/pages/inventory_page.dart';
import '../../../purchase_cart/presentation/pages/purchase_cart_page.dart';
import '../../domain/app_notification.dart';
import '../cubit/notifications_cubit.dart';
import '../cubit/notifications_state.dart';

class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key});

  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> {
  @override
  void initState() {
    super.initState();
    context.read<NotificationsCubit>().load();
  }

  /// "Mark all as read" and delete are pharmacist-only on the backend, so the
  /// control is hidden for employees rather than failing with a 401.
  bool get _isPharmacist =>
      context.read<AuthCubit>().session?.actor.type == AuthActorType.pharmacist;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,

      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(
          title: "Notifications",
          showNotificationBell: false,
        ),
      ),

      body: BlocBuilder<NotificationsCubit, NotificationsState>(
        builder: (context, state) => _body(context, state),
      ),
    );
  }

  Widget _body(BuildContext context, NotificationsState state) {
    if (state.status == NotificationsStatus.loading ||
        state.status == NotificationsStatus.initial) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.status == NotificationsStatus.failure) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                userFacingError(
                  state.error,
                  fallback: 'Unable to load notifications.',
                ),
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.errorRed),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                key: const ValueKey('notifications-retry-button'),
                onPressed: () => context.read<NotificationsCubit>().load(),
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }

    final items = state.feed.notifications;

    if (items.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => context.read<NotificationsCubit>().load(),
        child: ListView(
          children: const [
            SizedBox(height: 120),
            Center(
              child: Icon(
                Icons.notifications_none,
                size: 48,
                color: Colors.grey,
              ),
            ),
            SizedBox(height: 12),
            Center(child: Text("You have no notifications yet")),
          ],
        ),
      );
    }

    return Column(
      children: [
        if (_isPharmacist && state.unreadCount > 0)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '${state.unreadCount} unread',
                  style: const TextStyle(color: Colors.grey),
                ),
                TextButton.icon(
                  key: const ValueKey('mark-all-read-button'),
                  onPressed: state.busy
                      ? null
                      : () =>
                            context.read<NotificationsCubit>().markAllAsRead(),
                  icon: state.markingAll
                      ? const SizedBox.square(
                          dimension: 14,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.done_all, size: 18),
                  label: const Text('Mark all as read'),
                ),
              ],
            ),
          ),

        Expanded(
          child: RefreshIndicator(
            onRefresh: () => context.read<NotificationsCubit>().load(),
            child: ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: items.length,
              itemBuilder: (context, index) =>
                  _tile(context, state, items[index]),
            ),
          ),
        ),
      ],
    );
  }

  /// Marks the notification read, then opens what it is about.
  ///
  /// Read first and regardless of where it goes, because tapping it is the
  /// pharmacist saying they have seen it. A row that concerns nothing openable
  /// — an announcement, an approval — just goes quiet, which is honest: sending
  /// them to an arbitrary screen would be worse than leaving it inert.
  Future<void> _open(BuildContext context, AppNotification item) async {
    final navigator = Navigator.of(context);
    final cubit = context.read<NotificationsCubit>();

    if (!item.isRead) await cubit.markAsRead(item.id);

    final page = switch (item.destination) {
      NotificationDestination.purchaseCart => const PurchaseCartPage(),
      NotificationDestination.purchases => const PurchasesPage(),
      NotificationDestination.inventory => const InventoryPage(),
      NotificationDestination.salesHistory => const SaleHistoryPage(),
      NotificationDestination.none => null,
    };

    if (page == null) return;

    await navigator.push(MaterialPageRoute(builder: (_) => page));
  }

  Widget _tile(
    BuildContext context,
    NotificationsState state,
    AppNotification item,
  ) {
    final busy = state.mutatingId == item.id;

    return Card(
      color: item.isRead ? Colors.white : AppColors.veryLightGreen,
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: ListTile(
        // Marks it read and then goes where it is pointing. A notification
        // that only marks itself read is a chore: the pharmacist still has to
        // find the cart, the delivery or the shelf on their own.
        onTap: state.busy ? null : () => _open(context, item),
        leading: CircleAvatar(
          backgroundColor: (item.isRead ? Colors.grey : AppColors.lightGreen)
              .withValues(alpha: .15),
          child: Icon(
            _iconFor(item.type),
            size: 20,
            color: item.isRead ? Colors.grey : AppColors.darkGreen,
          ),
        ),
        title: Text(
          item.displayTitle,
          style: TextStyle(
            fontWeight: item.isRead ? FontWeight.w500 : FontWeight.bold,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(item.displayMessage, style: const TextStyle(fontSize: 13)),
            if (item.destination.isActionable)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(
                  children: [
                    Text(
                      item.destination.label,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: AppColors.darkGreen,
                      ),
                    ),
                    const Icon(
                      Icons.chevron_right,
                      size: 14,
                      color: AppColors.darkGreen,
                    ),
                  ],
                ),
              ),
            if (item.date != null)
              Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Text(
                  _formatDate(item.date!),
                  style: const TextStyle(fontSize: 11, color: Colors.grey),
                ),
              ),
          ],
        ),
        trailing: busy
            ? const SizedBox.square(
                dimension: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : (item.isRead
                  ? null
                  : Container(
                      width: 10,
                      height: 10,
                      decoration: const BoxDecoration(
                        color: AppColors.errorRed,
                        shape: BoxShape.circle,
                      ),
                    )),
      ),
    );
  }

  IconData _iconFor(String type) => switch (type) {
    'pharmacy_approved' => Icons.verified,
    'pharmacy_rejected' => Icons.cancel_outlined,
    'employee_approved' || 'employee' => Icons.people_alt_outlined,
    'order' => Icons.shopping_cart_outlined,
    'sale' => Icons.point_of_sale,
    'task' => Icons.task_alt,
    'low_stock' => Icons.warning_amber_outlined,
    'out_of_stock' => Icons.error_outline,
    _ => Icons.notifications_none,
  };

  String _formatDate(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-'
        '${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')}';
  }
}
