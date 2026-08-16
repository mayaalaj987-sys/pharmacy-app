<?php

namespace Tests\Feature\Documents;

use App\Models\Pharmacist;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileImageHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_profile_image_is_decoded_rerendered_and_metadata_is_removed(): void
    {
        [$pharmacist, $pharmacy, $token] = $this->authenticatedOwner();
        $jpeg = $this->jpegWithMetadata('confidential-exif-marker');

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->post('/api/profile/update', [
                'profile_image' => UploadedFile::fake()->createWithContent('portrait.jpg', $jpeg),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $stored = $pharmacist->fresh()->profile_image;
        $this->assertMatchesRegularExpression('#^profiles/[0-9a-f-]{36}\.jpg$#', $stored);
        $safe = Storage::disk('public')->get($stored);
        $this->assertStringNotContainsString('confidential-exif-marker', $safe);
        $this->assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->buffer($safe));
        $dimensions = getimagesizefromstring($safe);
        $this->assertSame(3, $dimensions[0]);
        $this->assertSame(4, $dimensions[1]);
        $this->assertNotFalse(@imagecreatefromstring($safe));
    }

    public function test_jpeg_png_and_webp_profile_images_are_accepted_and_randomly_named(): void
    {
        [$pharmacist, $pharmacy, $token] = $this->authenticatedOwner('formats');
        foreach (['jpg', 'png', 'webp'] as $format) {
            $this->withToken($token)
                ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
                ->post('/api/profile/update', [
                    'profile_image' => UploadedFile::fake()->createWithContent(
                        'client-name.'.$format,
                        $this->encodedImage($format),
                    ),
                ], ['Accept' => 'application/json'])
                ->assertOk();
            $stored = $pharmacist->fresh()->profile_image;
            $this->assertMatchesRegularExpression('#^profiles/[0-9a-f-]{36}\.'.$format.'$#', $stored);
            $this->assertStringNotContainsString('client-name', $stored);
        }
    }

    public function test_animated_malformed_dimension_bomb_and_oversized_images_are_rejected(): void
    {
        [, $pharmacy, $token] = $this->authenticatedOwner('invalid');
        $png = $this->png();
        $animated = $this->insertPngChunk($png, 'acTL', pack('NN', 2, 0));
        $bomb = $this->withPngDimensions($png, 10001, 10001);
        $files = [
            UploadedFile::fake()->createWithContent('animated.png', $animated),
            UploadedFile::fake()->createWithContent('bomb.png', $bomb),
            UploadedFile::fake()->createWithContent('broken.webp', 'RIFF'.pack('V', 4).'WEBP'),
        ];

        foreach ($files as $file) {
            $this->withToken($token)
                ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
                ->post('/api/profile/update', ['profile_image' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable();
        }

        $this->withToken($token)
            ->withHeader('X-Pharmacy-Id', (string) $pharmacy->id)
            ->post('/api/profile/update', [
                'profile_image' => UploadedFile::fake()->create('large.png', 2049, 'image/png'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('profile_image');

        $this->assertSame([], Storage::disk('public')->allFiles('profiles'));
    }

    private function authenticatedOwner(string $suffix = 'safe'): array
    {
        $pharmacist = Pharmacist::create([
            'name' => 'Owner',
            'email' => 'profile-'.$suffix.'@example.test',
            'password' => Hash::make('password'),
        ]);
        $pharmacy = Pharmacy::create([
            'pharmacist_id' => $pharmacist->id,
            'pharmacy_name' => 'Pharmacy',
            'pharmacy_address' => 'Address',
            'certificate' => '',
            'license' => '',
            'status' => 'approved',
        ]);

        return [$pharmacist, $pharmacy, $pharmacist->createToken('app', ['app'])->plainTextToken];
    }

    private function jpegWithMetadata(string $marker): string
    {
        $image = imagecreatetruecolor(4, 3);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 40, 20));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($image);
        $tiff = "II\x2A\x00\x08\x00\x00\x00"
            ."\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00\x06\x00\x00\x00"
            ."\x00\x00\x00\x00";
        $payload = "Exif\0\0".$tiff.$marker;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    private function encodedImage(string $format): string
    {
        $image = imagecreatetruecolor(2, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 120, 80));
        ob_start();
        match ($format) {
            'jpg' => imagejpeg($image, null, 90),
            'png' => imagepng($image, null, 6),
            'webp' => imagewebp($image, null, 90),
        };
        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    private function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
    }

    private function insertPngChunk(string $png, string $type, string $data): string
    {
        $typePosition = strrpos($png, 'IEND');
        $chunkPosition = $typePosition - 4;
        $chunkBody = $type.$data;
        $chunk = pack('N', strlen($data)).$chunkBody.pack('N', crc32($chunkBody));

        return substr($png, 0, $chunkPosition).$chunk.substr($png, $chunkPosition);
    }

    private function withPngDimensions(string $png, int $width, int $height): string
    {
        $ihdrData = pack('NN', $width, $height).substr($png, 24, 5);
        $body = 'IHDR'.$ihdrData;

        return substr($png, 0, 8).pack('N', 13).$body.pack('N', crc32($body)).substr($png, 33);
    }
}
