<?php

namespace App\Services;

use App\Exceptions\DocumentUploadException;
use Illuminate\Support\Facades\Storage;

class LegacyDocumentService
{
    private const PREFIXES = [
        'certificate' => 'certificates',
        'license' => 'licenses',
        'cv' => 'cvs',
        'experience_proof' => 'experience',
    ];

    public function __construct(private readonly PrivateDocumentService $documents) {}

    /** @return array{status:string,issue:?string,path:?string,metadata:?array} */
    public function inspect(?string $value, string $type, bool $jsonWrapped): array
    {
        $path = $this->extractPath($value, $jsonWrapped);
        if ($path === null) {
            return $this->result('missing', 'legacy_value_missing');
        }

        $prefix = self::PREFIXES[$type] ?? null;
        if ($prefix === null || ! $this->isSafeRelativePath($path, $prefix)) {
            return $this->result('invalid', 'legacy_path_invalid');
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return $this->result('missing', 'legacy_file_missing');
        }

        $absolute = $disk->path($path);
        $root = realpath($disk->path(''));
        $real = realpath($absolute);
        if ($root === false || $real === false || is_link($absolute)) {
            return $this->result('invalid', 'legacy_file_unsafe');
        }

        $rootPrefix = rtrim(str_replace('\\', '/', $root), '/').'/';
        $realNormalized = str_replace('\\', '/', $real);
        if (! str_starts_with($realNormalized, $rootPrefix)) {
            return $this->result('invalid', 'legacy_file_outside_storage');
        }

        try {
            $metadata = $this->documents->inspectPath($real);
        } catch (DocumentUploadException $exception) {
            return $this->result('invalid', $exception->errorCode);
        }

        return [
            'status' => 'valid',
            'issue' => null,
            'path' => $path,
            'metadata' => $metadata,
        ];
    }

    public function locatorHash(string $ownerType, int $ownerId, string $type, string $path): string
    {
        return hash('sha256', implode('|', [$ownerType, $ownerId, $type, $path]));
    }

    private function extractPath(?string $value, bool $jsonWrapped): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (! $jsonWrapped) {
            return trim($value);
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && count($decoded) === 1 && is_string($decoded[0])
            ? trim($decoded[0])
            : null;
    }

    private function isSafeRelativePath(string $path, string $prefix): bool
    {
        if ($path === '' || strlen($path) > 512 || str_contains($path, '%') || str_contains($path, '\\')) {
            return false;
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1 || str_contains($path, '..')) {
            return false;
        }

        return preg_match('#^'.preg_quote($prefix, '#').'/[A-Za-z0-9][A-Za-z0-9._-]*$#D', $path) === 1;
    }

    /** @return array{status:string,issue:string,path:null,metadata:null} */
    private function result(string $status, string $issue): array
    {
        return ['status' => $status, 'issue' => $issue, 'path' => null, 'metadata' => null];
    }
}
