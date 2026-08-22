import 'dart:io';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/layout/responsive_layout.dart';
import '../../../core/network/dio_client.dart';
import '../../../core/network/error_handler.dart';
import '../../auth/data/models/auth_api_exception.dart';

enum DocumentOwner { employee, pharmacy }

class SelfDocumentsPage extends StatefulWidget {
  const SelfDocumentsPage({super.key, required this.owner});

  final DocumentOwner owner;

  @override
  State<SelfDocumentsPage> createState() => _SelfDocumentsPageState();
}

class _SelfDocumentsPageState extends State<SelfDocumentsPage> {
  List<_DocumentVersion>? _documents;
  AuthApiException? _error;
  String? _busyType;

  String get _base => widget.owner == DocumentOwner.employee
      ? '/employee/documents'
      : '/pharmacy/documents';

  List<String> get _types => widget.owner == DocumentOwner.employee
      ? const ['cv', 'experience_proof']
      : const ['certificate', 'license'];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _error = null;
      _documents = null;
    });
    try {
      final response = await DioClient.dio.get(
        _base,
        options: widget.owner == DocumentOwner.employee
            ? Options(extra: {'skipActivePharmacy': true})
            : null,
      );
      final body = response.data;
      final raw = body is Map<String, dynamic> ? body['data'] : null;
      final documents = raw is List
          ? raw
                .whereType<Map<String, dynamic>>()
                .map(_DocumentVersion.fromJson)
                .toList(growable: false)
          : const <_DocumentVersion>[];
      if (mounted) setState(() => _documents = documents);
    } on DioException catch (error) {
      if (mounted) setState(() => _error = ErrorHandler.fromDio(error));
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = const AuthApiException(
            message: 'Unable to load your documents.',
          );
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final ownerLabel = widget.owner == DocumentOwner.employee
        ? 'My documents'
        : 'Pharmacy documents';
    return Scaffold(
      appBar: AppBar(title: Text(ownerLabel)),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            ResponsiveContent(
              safeArea: false,
              maxWidth: 760,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.owner == DocumentOwner.employee
                        ? 'Keep your CV and training proof up to date. A new upload creates a version and preserves your history.'
                        : 'Upload renewed legal documents here. New versions are reviewed by the administrator.',
                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 18),
                  for (final type in _types) ...[
                    _UploadCard(
                      label: _label(type),
                      busy: _busyType == type,
                      onUpload: _busyType == null ? () => _upload(type) : null,
                    ),
                    const SizedBox(height: 10),
                  ],
                  const Divider(height: 34),
                  Text(
                    'Version history',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 12),
                  _history(),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _history() {
    final error = _error;
    if (error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 28),
          child: Column(
            children: [
              Text(error.message, textAlign: TextAlign.center),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: _load,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
    }
    final documents = _documents;
    if (documents == null) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 36),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    if (documents.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 36),
        child: Center(child: Text('No documents uploaded yet.')),
      );
    }

    return Column(
      children: [
        for (final document in documents) ...[
          _DocumentCard(
            document: document,
            employeeDocument: widget.owner == DocumentOwner.employee,
            onOpen: () => _open(document),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }

  Future<void> _upload(String type) async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'png', 'jpg', 'jpeg'],
    );
    final file = result?.files.single;
    if (file == null || file.path == null || !mounted) return;
    if (file.size > 5 * 1024 * 1024) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('The document must be 5 MB or smaller.')),
      );
      return;
    }

    setState(() => _busyType = type);
    try {
      await DioClient.dio.post(
        '$_base/$type',
        data: FormData.fromMap({
          'document': await MultipartFile.fromFile(
            file.path!,
            filename: file.name,
          ),
        }),
        options: widget.owner == DocumentOwner.employee
            ? Options(extra: {'skipActivePharmacy': true})
            : null,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${_label(type)} uploaded successfully.')),
      );
      setState(() => _busyType = null);
      await _load();
    } on DioException catch (error) {
      if (!mounted) return;
      setState(() => _busyType = null);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(ErrorHandler.fromDio(error).message)),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _busyType = null);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Unable to upload the document.')),
      );
    }
  }

  Future<void> _open(_DocumentVersion document) async {
    setState(() => _busyType = 'open-${document.id}');
    try {
      final response = await DioClient.dio.get<List<int>>(
        document.downloadUrl,
        options: Options(
          responseType: ResponseType.bytes,
          extra: widget.owner == DocumentOwner.employee
              ? {'skipActivePharmacy': true}
              : null,
        ),
      );
      final directory = await getTemporaryDirectory();
      final path =
          '${directory.path}/${document.type}-v${document.version}.${document.extension}';
      final file = File(path);
      await file.writeAsBytes(response.data ?? const <int>[], flush: true);
      final result = await OpenFilex.open(path);
      if (mounted && result.type != ResultType.done) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('No app can open this file type.')),
        );
      }
    } on DioException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ErrorHandler.fromDio(error).message)),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Unable to open the document.')),
        );
      }
    } finally {
      if (mounted) setState(() => _busyType = null);
    }
  }

  static String _label(String type) => switch (type) {
    'cv' => 'CV',
    'experience_proof' => 'Training certificate',
    'certificate' => 'Pharmacy certificate',
    'license' => 'Pharmacy license',
    _ => type,
  };
}

class _UploadCard extends StatelessWidget {
  const _UploadCard({
    required this.label,
    required this.busy,
    required this.onUpload,
  });

  final String label;
  final bool busy;
  final VoidCallback? onUpload;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        leading: const Icon(Icons.description_outlined),
        title: Text(label),
        subtitle: const Text('PDF, PNG, or JPEG · maximum 5 MB'),
        trailing: busy
            ? const SizedBox.square(
                dimension: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.upload_file_rounded),
        onTap: onUpload,
      ),
    );
  }
}

class _DocumentCard extends StatelessWidget {
  const _DocumentCard({
    required this.document,
    required this.employeeDocument,
    required this.onOpen,
  });

  final _DocumentVersion document;
  final bool employeeDocument;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        leading: Icon(
          document.mimeType == 'application/pdf'
              ? Icons.picture_as_pdf_outlined
              : Icons.image_outlined,
          color: scheme.primary,
        ),
        title: Text(
          '${_SelfDocumentsPageState._label(document.type)} · v${document.version}',
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(document.sizeLabel),
            if (employeeDocument)
              Text('Viewed by ${document.viewerCount} pharmacies'),
            if (!employeeDocument && document.reviewStatus != null)
              Text('Review: ${document.reviewStatus}'),
            if (document.decisionReason != null)
              Text('Reason: ${document.decisionReason}'),
          ],
        ),
        trailing: const Icon(Icons.open_in_new_rounded),
        onTap: onOpen,
      ),
    );
  }
}

class _DocumentVersion {
  const _DocumentVersion({
    required this.id,
    required this.type,
    required this.version,
    required this.mimeType,
    required this.sizeBytes,
    required this.downloadUrl,
    required this.viewerCount,
    this.reviewStatus,
    this.decisionReason,
  });

  final int id;
  final String type;
  final int version;
  final String mimeType;
  final int sizeBytes;
  final String downloadUrl;
  final int viewerCount;
  final String? reviewStatus;
  final String? decisionReason;

  String get extension => switch (mimeType) {
    'application/pdf' => 'pdf',
    'image/png' => 'png',
    _ => 'jpg',
  };

  String get sizeLabel => sizeBytes < 1024 * 1024
      ? '${(sizeBytes / 1024).toStringAsFixed(0)} KB'
      : '${(sizeBytes / (1024 * 1024)).toStringAsFixed(1)} MB';

  factory _DocumentVersion.fromJson(Map<String, dynamic> json) {
    String? optional(dynamic value) {
      final text = value?.toString().trim();
      return text == null || text.isEmpty ? null : text;
    }

    return _DocumentVersion(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      type: json['type']?.toString() ?? '',
      version: int.tryParse(json['version']?.toString() ?? '') ?? 1,
      mimeType: json['mime_type']?.toString() ?? 'application/octet-stream',
      sizeBytes: int.tryParse(json['size_bytes']?.toString() ?? '') ?? 0,
      downloadUrl: json['download_url']?.toString() ?? '',
      viewerCount: int.tryParse(json['viewer_count']?.toString() ?? '') ?? 0,
      reviewStatus: optional(json['review_status']),
      decisionReason: optional(json['decision_reason']),
    );
  }
}
