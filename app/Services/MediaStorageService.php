<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MediaStorageService
{
    private const ALLOWED_FOLDERS = ['banners', 'blog', 'guides', 'media'];

    private const ALLOWED_IMAGE_TYPES = [
        IMAGETYPE_JPEG => ['ext' => 'jpg', 'mime' => 'image/jpeg'],
        IMAGETYPE_PNG => ['ext' => 'png', 'mime' => 'image/png'],
        IMAGETYPE_WEBP => ['ext' => 'webp', 'mime' => 'image/webp'],
        IMAGETYPE_GIF => ['ext' => 'gif', 'mime' => 'image/gif'],
    ];

    public function isR2Enabled(): bool
    {
        return config('filesystems.media_disk') === 'r2'
            && filled(config('filesystems.disks.r2.key'))
            && filled(config('filesystems.disks.r2.secret'))
            && filled(config('filesystems.disks.r2.bucket'))
            && filled(config('filesystems.disks.r2.endpoint'))
            && filled(config('filesystems.disks.r2.url'));
    }

    /**
     * Validate raster image, optionally optimize to WebP, upload to Cloudflare R2 (or local public fallback),
     * and return clean CDN metadata & URL.
     */
    public function uploadImage(
        UploadedFile $file,
        string $folder = 'media',
        ?string $namePrefix = null,
        bool $convertToWebp = true
    ): array {
        $realPath = $file->getRealPath();
        if (!$realPath || !is_file($realPath)) {
            throw new RuntimeException('Không đọc được tệp ảnh tải lên.');
        }

        // Strict binary image signature verification (blocks SVG/HTML/PHP/script uploads)
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false || !isset($imageInfo[2]) || !isset(self::ALLOWED_IMAGE_TYPES[$imageInfo[2]])) {
            throw new RuntimeException('Chỉ chấp nhận ảnh thật định dạng JPG, PNG, WebP hoặc GIF.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 8000 || $height > 8000) {
            throw new RuntimeException('Kích thước ảnh không hợp lệ (tối đa 8000×8000px).');
        }

        $typeMeta = self::ALLOWED_IMAGE_TYPES[$imageInfo[2]];
        $bytes = file_get_contents($realPath);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Nội dung ảnh trống hoặc bị lỗi.');
        }

        $ext = $typeMeta['ext'];
        $mime = $typeMeta['mime'];

        // Optimize JPEG / PNG to WebP when GD supports it and output is smaller
        if ($convertToWebp && in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            $webpBytes = $this->tryConvertToWebp($realPath, $imageInfo[2]);
            if ($webpBytes !== null && strlen($webpBytes) > 0 && strlen($webpBytes) < strlen($bytes)) {
                $bytes = $webpBytes;
                $ext = 'webp';
                $mime = 'image/webp';
            }
        }

        $safeFolder = in_array($folder, self::ALLOWED_FOLDERS, true) ? $folder : 'media';
        $prefix = $namePrefix ? (Str::slug($namePrefix) . '_') : '';
        $filename = sprintf('%s%s_%s.%s', $prefix, now()->format('YmdHis'), Str::lower(Str::random(8)), $ext);
        $key = sprintf('%s/%s/%s/%s', $safeFolder, now()->format('Y'), now()->format('m'), $filename);

        if ($this->isR2Enabled()) {
            $this->putObjectToR2($key, $bytes, $mime);
            $cdnBase = rtrim((string) config('filesystems.disks.r2.url'), '/');

            return [
                'url' => $cdnBase . '/' . $key,
                'key' => $key,
                'width' => $width,
                'height' => $height,
                'size' => strlen($bytes),
                'mime' => $mime,
                'storage' => 'r2',
            ];
        }

        // Local public fallback when MEDIA_DISK != r2
        $relativeDir = 'uploads/' . $safeFolder . '/' . now()->format('Y') . '/' . now()->format('m');
        $fullDir = public_path($relativeDir);
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }
        file_put_contents($fullDir . DIRECTORY_SEPARATOR . $filename, $bytes);

        return [
            'url' => '/' . $relativeDir . '/' . $filename,
            'key' => $key,
            'width' => $width,
            'height' => $height,
            'size' => strlen($bytes),
            'mime' => $mime,
            'storage' => 'local',
        ];
    }

    /**
     * Delete an uploaded object from R2 or local uploads by its public URL.
     */
    public function deleteByUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        $cdnBase = rtrim((string) config('filesystems.disks.r2.url'), '/');
        if ($this->isR2Enabled() && $cdnBase !== '' && str_starts_with($url, $cdnBase . '/')) {
            $key = ltrim(substr($url, strlen($cdnBase)), '/');
            if ($key === '' || str_contains($key, '..')) {
                return false;
            }
            try {
                $this->deleteObjectFromR2($key);
                return true;
            } catch (\Throwable $e) {
                Log::warning('R2 delete failed', ['key' => $key, 'error' => $e->getMessage()]);
                return false;
            }
        }

        if (str_starts_with($url, '/uploads/') && !str_contains($url, '..')) {
            $localPath = public_path(ltrim($url, '/'));
            if (is_file($localPath)) {
                return @unlink($localPath);
            }
        }

        return false;
    }

    private function tryConvertToWebp(string $realPath, int $imageType): ?string
    {
        if (!function_exists('imagewebp')) {
            return null;
        }

        $img = null;
        if ($imageType === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
            $img = @imagecreatefromjpeg($realPath);
        } elseif ($imageType === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
            $img = @imagecreatefrompng($realPath);
            if ($img) {
                if (function_exists('imagepalettetotruecolor')) {
                    @imagepalettetotruecolor($img);
                }
                @imagealphablending($img, true);
                @imagesavealpha($img, true);
            }
        }

        if (!$img) {
            return null;
        }

        ob_start();
        $ok = @imagewebp($img, null, 84);
        $webp = ob_get_clean();
        imagedestroy($img);

        return ($ok && is_string($webp) && $webp !== '') ? $webp : null;
    }

    private function putObjectToR2(string $key, string $bytes, string $contentType): void
    {
        $headers = [
            'cache-control' => 'public, max-age=31536000, immutable',
            'content-type' => $contentType,
        ];

        $response = $this->sendSignedR2Request('PUT', $key, $bytes, $headers);
        if (!$response->successful()) {
            Log::error('Cloudflare R2 PUT failed', [
                'key' => $key,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
            throw new RuntimeException('Tải ảnh lên Cloudflare R2 thất bại (HTTP ' . $response->status() . ').');
        }
    }

    private function deleteObjectFromR2(string $key): void
    {
        $response = $this->sendSignedR2Request('DELETE', $key, '', []);
        if (!$response->successful() && $response->status() !== 404) {
            throw new RuntimeException('Xóa tệp trên Cloudflare R2 thất bại (HTTP ' . $response->status() . ').');
        }
    }

    private function sendSignedR2Request(string $method, string $key, string $payload, array $extraHeaders)
    {
        $accessKey = (string) config('filesystems.disks.r2.key');
        $secretKey = (string) config('filesystems.disks.r2.secret');
        $bucket = (string) config('filesystems.disks.r2.bucket');
        $endpoint = rtrim((string) config('filesystems.disks.r2.endpoint'), '/');

        $parsed = parse_url($endpoint);
        $host = $parsed['host'] ?? '';
        if ($host === '') {
            throw new RuntimeException('R2_ENDPOINT không hợp lệ.');
        }

        $region = 'auto';
        $service = 's3';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $payload);

        $encodedKey = implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
        $canonicalUri = '/' . rawurlencode($bucket) . '/' . $encodedKey;

        $headers = array_merge($extraHeaders, [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ]);
        ksort($headers);

        $canonicalHeaders = '';
        $signedHeaderNames = [];
        foreach ($headers as $name => $value) {
            $lower = strtolower($name);
            $canonicalHeaders .= $lower . ':' . trim((string) $value) . "\n";
            $signedHeaderNames[] = $lower;
        }
        $signedHeaders = implode(';', $signedHeaderNames);

        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            '', // canonical query string
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $credentialScope = "{$dateStamp}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $accessKey,
            $credentialScope,
            $signedHeaders,
            $signature
        );

        $requestHeaders = [
            'Authorization' => $authorization,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ];
        if (isset($extraHeaders['cache-control'])) {
            $requestHeaders['Cache-Control'] = $extraHeaders['cache-control'];
        }

        $url = $endpoint . $canonicalUri;
        $client = Http::timeout(25)->withHeaders($requestHeaders);

        if ($method === 'PUT') {
            return $client->withBody($payload, $extraHeaders['content-type'] ?? 'application/octet-stream')->put($url);
        }

        return $client->delete($url);
    }
}
