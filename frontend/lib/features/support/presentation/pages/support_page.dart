import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/custom_app_bar.dart';
import '../../../../core/widgets/custom_button.dart';
import '../../domain/support_ticket.dart';
import '../cubit/support_cubit.dart';
import '../cubit/support_state.dart';

/// Contact support and read the replies.
///
/// Reachable by both pharmacists and employees, and deliberately usable even
/// when the pharmacy context is unavailable — that is often the reason someone
/// is writing in.
class SupportPage extends StatefulWidget {
  const SupportPage({super.key});

  @override
  State<SupportPage> createState() => _SupportPageState();
}

class _SupportPageState extends State<SupportPage> {
  final _subject = TextEditingController();
  final _message = TextEditingController();

  @override
  void initState() {
    super.initState();
    context.read<SupportCubit>().load();
  }

  @override
  void dispose() {
    _subject.dispose();
    _message.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: const PreferredSize(
        preferredSize: Size.fromHeight(60),
        child: CustomAppBar(title: 'Support', showNotificationBell: false),
      ),
      body: BlocBuilder<SupportCubit, SupportState>(
        builder: (context, state) {
          return RefreshIndicator(
            onRefresh: () => context.read<SupportCubit>().load(),
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                const Text(
                  'Send a message',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 4),
                const Text(
                  'An administrator reads every message and replies here.',
                  style: TextStyle(color: Colors.grey, fontSize: 12),
                ),
                const SizedBox(height: 16),
                TextField(
                  key: const ValueKey('support-subject-field'),
                  controller: _subject,
                  enabled: !state.sending,
                  maxLength: 150,
                  decoration: const InputDecoration(
                    labelText: 'Subject',
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  key: const ValueKey('support-message-field'),
                  controller: _message,
                  enabled: !state.sending,
                  maxLines: 5,
                  maxLength: 2000,
                  decoration: const InputDecoration(
                    labelText: 'How can we help?',
                    alignLabelWithHint: true,
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 8),
                CustomButton(
                  key: const ValueKey('support-send-button'),
                  text: 'Send',
                  isLoading: state.sending,
                  onPressed: state.sending ? null : _send,
                ),

                const Divider(height: 40),

                Row(
                  children: [
                    const Text(
                      'Your messages',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const Spacer(),
                    if (state.openCount > 0)
                      Text(
                        '${state.openCount} awaiting reply',
                        style: const TextStyle(
                          color: AppColors.pendingOrange,
                          fontSize: 12,
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                ..._history(context, state),
              ],
            ),
          );
        },
      ),
    );
  }

  List<Widget> _history(BuildContext context, SupportState state) {
    if (state.status == SupportStatus.loading ||
        state.status == SupportStatus.initial) {
      return const [
        Padding(
          padding: EdgeInsets.all(24),
          child: Center(child: CircularProgressIndicator()),
        ),
      ];
    }

    if (state.status == SupportStatus.failure) {
      return [
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              Text(
                userFacingError(
                  state.error,
                  fallback: 'Unable to load your messages.',
                ),
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.errorRed),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                key: const ValueKey('support-retry-button'),
                onPressed: () => context.read<SupportCubit>().load(),
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      ];
    }

    if (state.tickets.isEmpty) {
      return const [
        Padding(
          padding: EdgeInsets.symmetric(vertical: 24),
          child: Text(
            'You have not contacted support yet.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.grey),
          ),
        ),
      ];
    }

    return state.tickets.map(_ticketCard).toList();
  }

  Widget _ticketCard(SupportTicket ticket) {
    return Card(
      key: ValueKey('support-ticket-${ticket.id}'),
      color: AppColors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    ticket.subject,
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color:
                        (ticket.isOpen
                                ? AppColors.pendingOrange
                                : AppColors.successGreen)
                            .withValues(alpha: .15),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    ticket.statusLabel,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: ticket.isOpen
                          ? AppColors.pendingOrange
                          : AppColors.successGreen,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(ticket.message),
            if (ticket.hasResponse) ...[
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.veryLightGreen,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Support replied',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: AppColors.darkGreen,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(ticket.adminResponse!),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _send() async {
    final messenger = ScaffoldMessenger.of(context);
    final cubit = context.read<SupportCubit>();

    final subject = _subject.text.trim();
    final message = _message.text.trim();

    // Mirrors the backend rules so an obviously invalid message is not sent.
    if (subject.length < 3 || message.length < 10) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text(
            'Enter a subject of at least 3 characters and a message of at '
            'least 10.',
          ),
        ),
      );
      return;
    }

    final ok = await cubit.send(subject: subject, message: message);
    if (!mounted) return;

    if (ok) {
      _subject.clear();
      _message.clear();
    }

    messenger.showSnackBar(
      SnackBar(
        backgroundColor: ok ? AppColors.tealGreen : AppColors.errorRed,
        content: Text(
          ok
              ? 'Your message was sent to support.'
              : userFacingError(
                  cubit.state.error,
                  fallback: 'Unable to send your message.',
                ),
        ),
      ),
    );
  }
}
