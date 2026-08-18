import 'dart:io';

import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../../core/network/user_facing_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../auth/data/models/auth_api_exception.dart';
import '../../data/employees_repository.dart';
import '../../domain/applicant_document.dart';

/// An applicant's CV and training certificate, before deciding to offer.
///
/// The files are fetched over the authenticated client and handed to the
/// phone's own viewer. They cannot simply be opened as links: the routes
/// require a bearer token, and a browser would not send one.
///
/// Opening one is not a passive read — the backend records who looked and
/// tells the applicant — so the sheet says so rather than letting a recruiter
/// discover it later.
class ApplicantDocumentsSheet extends StatefulWidget {
  final int employeeId;
  final String applicantName;
  final EmployeesRepository repository;

  const ApplicantDocumentsSheet({
    super.key,
    required this.employeeId,
    required this.applicantName,
    required this.repository,
  });

  @override
  State<ApplicantDocumentsSheet> createState() =>
      _ApplicantDocumentsSheetState();
}

class _ApplicantDocumentsSheetState extends State<ApplicantDocumentsSheet> {
  List<ApplicantDocument>? _documents;
  AuthApiException? _error;
  String? _openingId;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final documents = await widget.repository.fetchApplicantDocuments(
        widget.employeeId,
      );
      if (mounted) setState(() => _documents = documents);
    } on AuthApiException catch (error) {
      if (mounted) setState(() => _error = error);
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = const AuthApiException(
            message: 'Unable to load this applicant\'s documents.',
          );
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.applicantName,
              style: const TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.bold,
                color: AppColors.darkGreen,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Opening a document is recorded, and the applicant is told which '
              'pharmacy looked.',
              style: TextStyle(fontSize: 11, color: Colors.black54),
            ),
            const SizedBox(height: 16),
            _body(),
          ],
        ),
      ),
    );
  }

  Widget _body() {
    if (_error != null) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 20),
        child: Text(
          userFacingError(_error, fallback: 'Unable to load the documents.'),
        ),
      );
    }

    final documents = _documents;
    if (documents == null) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 28),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (documents.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 20),
        child: Text('This applicant has not uploaded any documents.'),
      );
    }

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: documents.map(_documentTile).toList(),
    );
  }

  Widget _documentTile(ApplicantDocument document) {
    final busy = _openingId == document.id;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: ListTile(
        key: ValueKey('applicant-document-${document.id}'),
        leading: Icon(
          document.mimeType == 'application/pdf'
              ? Icons.picture_as_pdf
              : Icons.image_outlined,
          color: AppColors.darkGreen,
        ),
        title: Text(document.label),
        subtitle: Text('${document.sizeLabel} · v${document.version}'),
        trailing: busy
            ? const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.open_in_new, size: 18),
        onTap: busy ? null : () => _open(document),
      ),
    );
  }

  Future<void> _open(ApplicantDocument document) async {
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _openingId = document.id);

    try {
      final bytes = await widget.repository.downloadDocument(
        document.downloadUrl,
      );

      // A cache directory, not documents: this is a copy of somebody else's
      // personal file and has no business persisting on the recruiter's phone
      // any longer than it takes to read it.
      final directory = await getTemporaryDirectory();
      final file = File(
        '${directory.path}/applicant-${widget.employeeId}-'
        '${document.type}.${document.fileExtension}',
      );
      await file.writeAsBytes(bytes, flush: true);

      final result = await OpenFilex.open(file.path);
      if (!mounted) return;

      if (result.type != ResultType.done) {
        messenger.showSnackBar(
          SnackBar(
            content: Text(
              'No app on this phone can open a ${document.fileExtension} file.',
            ),
          ),
        );
      }
    } on AuthApiException catch (error) {
      if (mounted) {
        messenger.showSnackBar(
          SnackBar(
            content: Text(
              userFacingError(error, fallback: 'Unable to open the document.'),
            ),
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        messenger.showSnackBar(
          const SnackBar(content: Text('Unable to open the document.')),
        );
      }
    } finally {
      if (mounted) setState(() => _openingId = null);
    }
  }
}
