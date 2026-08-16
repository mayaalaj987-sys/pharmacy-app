<?php

namespace App\Services;

use App\Exceptions\DocumentUploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileImageService
{
    private const DIRECTORY = 'profiles';

    private const MAX_BYTES = 2 * 1024 * 1024;

    private const MAX_DIMENSION = 10000;

    private const MAX_PIXELS = 30_000_000;

    private const OUTPUT_MAX_EDGE = 2048;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(UploadedFile $image): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new DocumentUploadException(
                'Secure profile image processing is unavailable.',
                'image_processing_unavailable',
                503,
            );
        }

        $path = $image->getRealPath();
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new DocumentUploadException('The profile image may not be larger than 2 MiB.', 'profile_image_too_large');
        }

        $contents = file_get_contents($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($contents === false || ! is_string($mime) || ! isset(self::MIME_EXTENSIONS[$mime])) {
            throw new DocumentUploadException('Only valid JPEG, PNG, and WebP profile images are accepted.', 'profile_image_invalid');
        }

        $extension = strtolower($image->getClientOriginalExtension());
        $allowedExtensions = match ($mime) {
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        };
        if (! in_array($extension, $allowedExtensions, true)) {
            throw new DocumentUploadException('The profile image extension does not match its verified content.', 'profile_image_invalid');
        }

        $this->rejectAnimatedOrTrailingContent($contents, $mime);
        $dimensions = @getimagesizefromstring($contents);
        if ($dimensions === false) {
            throw new DocumentUploadException('The profile image could not be decoded.', 'profile_image_invalid');
        }

        [$width, $height] = $dimensions;
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION
            || ($width * $height) > self::MAX_PIXELS) {
            throw new DocumentUploadException('The profile image dimensions are unsafe.', 'profile_image_dimensions_invalid');
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new DocumentUploadException('The profile image could not be decoded.', 'profile_image_invalid');
        }

        try {
            if ($mime === 'image/jpeg') {
                $source = $this->autoOrient($source, $path);
                $width = imagesx($source);
                $height = imagesy($source);
            }

            $scale = min(1, self::OUTPUT_MAX_EDGE / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $rendered = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($rendered === false) {
                throw new DocumentUploadException('The profile image could not be processed.', 'profile_image_processing_failed', 500);
            }

            if ($mime !== 'image/jpeg') {
                imagealphablending($rendered, false);
                imagesavealpha($rendered, true);
                $transparent = imagecolorallocatealpha($rendered, 0, 0, 0, 127);
                imagefilledrectangle($rendered, 0, 0, $targetWidth, $targetHeight, $transparent);
            }

            if (! imagecopyresampled($rendered, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
                imagedestroy($rendered);
                throw new DocumentUploadException('The profile image could not be processed.', 'profile_image_processing_failed', 500);
            }

            ob_start();
            $encoded = match ($mime) {
                'image/jpeg' => imagejpeg($rendered, null, 88),
                'image/png' => imagepng($rendered, null, 6),
                'image/webp' => imagewebp($rendered, null, 88),
            };
            $safeContents = ob_get_clean();
            imagedestroy($rendered);
            if (! $encoded || ! is_string($safeContents) || $safeContents === '') {
                throw new DocumentUploadException('The profile image could not be encoded.', 'profile_image_processing_failed', 500);
            }

            $filename = Str::uuid()->toString().'.'.self::MIME_EXTENSIONS[$mime];
            $storagePath = self::DIRECTORY.'/'.$filename;
            $written = Storage::disk('public')->put($storagePath, $safeContents);
            if ($written !== true || ! Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->delete($storagePath);
                throw new DocumentUploadException('The profile image could not be stored.', 'profile_image_storage_failed', 500);
            }

            return $storagePath;
        } finally {
            if ($source instanceof \GdImage) {
                imagedestroy($source);
            }
        }
    }

    public function url(?string $stored): ?string
    {
        $path = $this->ownedPath($stored);
        $baseUrl = config('filesystems.disks.public.url');
        if ($path === null || ! is_string($baseUrl) || preg_match('#^https?://#i', $baseUrl) !== 1) {
            return null;
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

        return rtrim($baseUrl, '/').'/'.$encodedPath;
    }

    public function deleteOwned(?string $stored): void
    {
        $path = $this->ownedPath($stored);
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }

    public function ownedPath(?string $stored): ?string
    {
        if (! is_string($stored) || trim($stored) === '') {
            return null;
        }

        $decoded = json_decode($stored, true);
        $path = is_array($decoded) && count($decoded) === 1 ? ($decoded[0] ?? null) : $stored;
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);
        if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, '%')) {
            return null;
        }

        return preg_match('/^profiles\/[A-Za-z0-9][A-Za-z0-9._-]*$/D', $path) === 1 ? $path : null;
    }

    private function rejectAnimatedOrTrailingContent(string $contents, string $mime): void
    {
        if ($mime === 'image/png') {
            if (! str_starts_with($contents, "\x89PNG\r\n\x1a\n") || ! str_ends_with($contents, "IEND\xAE\x42\x60\x82")) {
                throw new DocumentUploadException('The PNG profile image is malformed.', 'profile_image_invalid');
            }
            if (str_contains($contents, 'acTL')) {
                throw new DocumentUploadException('Animated profile images are not accepted.', 'profile_image_animated');
            }
        }

        if ($mime === 'image/jpeg' && ! str_ends_with($contents, "\xFF\xD9")) {
            throw new DocumentUploadException('The JPEG profile image is malformed.', 'profile_image_invalid');
        }

        if ($mime === 'image/webp') {
            if (! str_starts_with($contents, 'RIFF') || substr($contents, 8, 4) !== 'WEBP'
                || unpack('V', substr($contents, 4, 4))[1] + 8 !== strlen($contents)) {
                throw new DocumentUploadException('The WebP profile image is malformed.', 'profile_image_invalid');
            }
            if (str_contains($contents, 'ANIM') || str_contains($contents, 'ANMF')) {
                throw new DocumentUploadException('Animated profile images are not accepted.', 'profile_image_animated');
            }
        }
    }

    private function autoOrient(\GdImage $image, string $path): \GdImage
    {
        $orientation = 1;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path, 'IFD0', true, false);
            $orientation = (int) ($exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? 1);
        }

        if (in_array($orientation, [2, 4, 5, 7], true) && function_exists('imageflip')) {
            imageflip($image, in_array($orientation, [2, 5, 7], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated !== false) {
                imagedestroy($image);

                return $rotated;
            }
        }

        return $image;
    }
}
