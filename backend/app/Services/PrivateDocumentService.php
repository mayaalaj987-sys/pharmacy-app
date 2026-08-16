<?php

namespace App\Services;

use App\Exceptions\DocumentUploadException;
use App\Models\PharmacyDocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivateDocumentService
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const DISK = 'documents';

    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function storeUpload(UploadedFile $file, string $scope, string $type): StoredDocument
    {
        $metadata = $this->inspectPath($file->getRealPath());
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = match ($metadata['mime_type']) {
            'application/pdf' => ['pdf'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
        };
        if (! in_array($extension, $allowedExtensions, true)) {
            throw new DocumentUploadException(
                'The document extension does not match its verified content.',
                'document_extension_mismatch',
            );
        }

        return $this->storeValidatedPath($file->getRealPath(), $scope, $type, $metadata);
    }

    public function storePath(string $sourcePath, string $scope, string $type): StoredDocument
    {
        $metadata = $this->inspectPath($sourcePath);

        return $this->storeValidatedPath($sourcePath, $scope, $type, $metadata);
    }

    /** @param array{mime_type:string,byte_size:int,sha256:string} $metadata */
    private function storeValidatedPath(string $sourcePath, string $scope, string $type, array $metadata): StoredDocument
    {
        $allowed = [
            'pharmacy-documents' => ['certificate', 'license'],
            'employee-documents' => ['cv', 'experience_proof'],
        ];
        if (! isset($allowed[$scope]) || ! in_array($type, $allowed[$scope], true)) {
            throw new \InvalidArgumentException('Unsupported private document scope or type.');
        }
        $storageKey = sprintf(
            '%s/%s/%s.%s',
            $scope,
            $type,
            Str::uuid()->toString(),
            self::MIME_EXTENSIONS[$metadata['mime_type']],
        );

        $contents = file_get_contents($sourcePath);
        if ($contents === false) {
            throw new DocumentUploadException('The uploaded document could not be read.', 'document_read_failed', 500);
        }

        try {
            $written = Storage::disk(self::DISK)->put($storageKey, $contents, ['visibility' => 'private']);
        } catch (\Throwable $exception) {
            $this->delete($storageKey);
            throw new DocumentUploadException('The document could not be stored.', 'document_storage_failed', 500);
        }

        try {
            $disk = Storage::disk(self::DISK);
            if ($written !== true || ! $disk->exists($storageKey)
                || $disk->size($storageKey) !== $metadata['byte_size']
                || ! hash_equals($metadata['sha256'], hash('sha256', $disk->get($storageKey)))) {
                $this->delete($storageKey);
                throw new DocumentUploadException('Stored document verification failed.', 'document_storage_verification_failed', 500);
            }
        } catch (DocumentUploadException $exception) {
            throw $exception;
        } catch (\Throwable) {
            $this->delete($storageKey);
            throw new DocumentUploadException('Stored document verification failed.', 'document_storage_verification_failed', 500);
        }

        return new StoredDocument(
            $storageKey,
            $metadata['mime_type'],
            $metadata['byte_size'],
            $metadata['sha256'],
        );
    }

    /** @return array{mime_type:string,byte_size:int,sha256:string} */
    public function inspectPath(string $path): array
    {
        if (! is_file($path) || is_link($path)) {
            throw new DocumentUploadException('The uploaded document is not a regular file.');
        }

        $size = filesize($path);
        if ($size === false || $size < 1) {
            throw new DocumentUploadException('The uploaded document is empty.');
        }
        if ($size > self::MAX_BYTES) {
            throw new DocumentUploadException('The document may not be larger than 5 MiB.', 'document_too_large');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! is_string($mime) || ! array_key_exists($mime, self::MIME_EXTENSIONS)) {
            throw new DocumentUploadException('Only valid PDF, JPEG, and PNG documents are accepted.', 'document_type_unsupported');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new DocumentUploadException('The uploaded document could not be read.', 'document_read_failed');
        }

        match ($mime) {
            'application/pdf' => $this->validatePdf($contents),
            'image/jpeg', 'image/png' => $this->validateImage($contents, $mime),
        };

        return [
            'mime_type' => $mime,
            'byte_size' => (int) $size,
            'sha256' => hash('sha256', $contents),
        ];
    }

    public function delete(string $storageKey): void
    {
        if ($this->isOwnedStorageKey($storageKey)) {
            try {
                Storage::disk(self::DISK)->delete($storageKey);
            } catch (\Throwable $exception) {
                logger()->warning('Private document cleanup failed.', [
                    'exception_class' => $exception::class,
                ]);
            }
        }
    }

    public function isOwnedStorageKey(?string $storageKey): bool
    {
        return is_string($storageKey)
            && preg_match('#^(pharmacy-documents|employee-documents)/(certificate|license|cv|experience_proof)/[0-9a-f-]{36}\.(pdf|jpg|png)$#D', $storageKey) === 1;
    }

    public function verifiedPharmacyDocumentContents(PharmacyDocumentVersion $document): ?string
    {
        $key = $document->storage_key;
        if (! is_string($key)
            || preg_match('#^pharmacy-documents/'.preg_quote($document->document_type, '#').'/[0-9a-f-]{36}\.(pdf|jpg|png)$#D', $key) !== 1
            || ! in_array($document->verified_mime_type, array_keys(self::MIME_EXTENSIONS), true)) {
            return null;
        }

        try {
            $disk = Storage::disk(self::DISK);
            if (! $disk->exists($key)) {
                return null;
            }
            $absolute = $disk->path($key);
            $root = realpath($disk->path(''));
            $real = realpath($absolute);
            if ($root === false || $real === false || ! is_file($absolute) || is_link($absolute)) {
                return null;
            }
            $rootPrefix = rtrim(str_replace('\\', '/', $root), '/').'/';
            if (! str_starts_with(str_replace('\\', '/', $real), $rootPrefix)) {
                return null;
            }

            $metadata = $this->inspectPath($real);
            if ($metadata['mime_type'] !== $document->verified_mime_type
                || $metadata['byte_size'] !== (int) $document->byte_size
                || ! hash_equals($document->sha256, $metadata['sha256'])) {
                return null;
            }

            $contents = file_get_contents($real);

            return is_string($contents) ? $contents : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function validatePdf(string $contents): void
    {
        if (! str_starts_with($contents, '%PDF-') || preg_match('/^%PDF-1\.[0-7]\R/', $contents) !== 1) {
            throw new DocumentUploadException('The PDF is malformed.', 'document_malformed');
        }

        if (preg_match('/startxref\s+(\d+)\s+%%EOF\s*\z/s', $contents, $match) !== 1) {
            throw new DocumentUploadException('The PDF is malformed.', 'document_malformed');
        }

        $xrefOffset = (int) $match[1];
        if ($xrefOffset < 1 || $xrefOffset >= strlen($contents)) {
            throw new DocumentUploadException('The PDF cross-reference data is invalid.', 'document_malformed');
        }

        $xref = substr($contents, $xrefOffset, 80);
        if (! str_starts_with($xref, 'xref') && ! str_contains($xref, '/XRef')) {
            throw new DocumentUploadException('The PDF cross-reference data is invalid.', 'document_malformed');
        }

        if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA)\b#i', $contents) === 1) {
            throw new DocumentUploadException('Active or embedded PDF content is not accepted.', 'document_unsafe_content');
        }
    }

    private function validateImage(string $contents, string $mime): void
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new DocumentUploadException('Secure image validation is unavailable.', 'image_processing_unavailable', 503);
        }

        if ($mime === 'image/png') {
            if (! str_starts_with($contents, "\x89PNG\r\n\x1a\n") || ! str_ends_with($contents, "IEND\xAE\x42\x60\x82")) {
                throw new DocumentUploadException('The PNG image is malformed.', 'document_malformed');
            }
            if (str_contains($contents, 'acTL')) {
                throw new DocumentUploadException('Animated images are not accepted.', 'document_animated_image');
            }
        }

        if ($mime === 'image/jpeg' && ! str_ends_with($contents, "\xFF\xD9")) {
            throw new DocumentUploadException('The JPEG image is malformed.', 'document_malformed');
        }

        $dimensions = @getimagesizefromstring($contents);
        if ($dimensions === false) {
            throw new DocumentUploadException('The image could not be decoded.', 'document_malformed');
        }
        [$width, $height] = $dimensions;
        if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || ($width * $height) > 40_000_000) {
            throw new DocumentUploadException('The image dimensions are unsafe.', 'document_image_dimensions_invalid');
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new DocumentUploadException('The image could not be decoded.', 'document_malformed');
        }
        imagedestroy($image);
    }
}
