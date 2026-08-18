/// One of an applicant's recruitment documents, as a recruiter may see it.
///
/// Carries no bytes and no storage key — only enough to decide whether to open
/// it, plus the authenticated URLs that will serve it. Opening one is a logged
/// act that the applicant is told about.
class ApplicantDocument {
  /// The public id, not the row id: the backend routes on this.
  final String id;

  /// `cv` or `experience_proof`.
  final String type;

  final int version;
  final String mimeType;
  final int sizeBytes;
  final DateTime? uploadedAt;
  final String previewUrl;
  final String downloadUrl;

  const ApplicantDocument({
    required this.id,
    required this.type,
    required this.version,
    required this.mimeType,
    required this.sizeBytes,
    required this.previewUrl,
    required this.downloadUrl,
    this.uploadedAt,
  });

  String get label => switch (type) {
    'cv' => 'CV',
    'experience_proof' => 'Training certificate',
    _ => type,
  };

  String get sizeLabel {
    if (sizeBytes < 1024) return '$sizeBytes B';
    if (sizeBytes < 1024 * 1024) {
      return '${(sizeBytes / 1024).toStringAsFixed(0)} KB';
    }
    return '${(sizeBytes / (1024 * 1024)).toStringAsFixed(1)} MB';
  }

  /// The extension to save under, so the phone's viewer picks the right app.
  String get fileExtension => switch (mimeType) {
    'application/pdf' => 'pdf',
    'image/png' => 'png',
    _ => 'jpg',
  };

  factory ApplicantDocument.fromJson(Map<String, dynamic> json) {
    final rawUploaded = json['uploaded_at']?.toString();
    final rawSize = json['size_bytes'];
    final rawVersion = json['version'];

    return ApplicantDocument(
      id: json['id']?.toString() ?? '',
      type: json['type']?.toString() ?? '',
      version: rawVersion is num ? rawVersion.toInt() : 1,
      mimeType: json['mime_type']?.toString() ?? 'application/octet-stream',
      sizeBytes: rawSize is num ? rawSize.toInt() : 0,
      uploadedAt: rawUploaded == null || rawUploaded.isEmpty
          ? null
          : DateTime.tryParse(rawUploaded),
      previewUrl: json['preview_url']?.toString() ?? '',
      downloadUrl: json['download_url']?.toString() ?? '',
    );
  }
}
