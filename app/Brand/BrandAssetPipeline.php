<?php

namespace App\Brand;

use App\Rules\SafeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Content-addressed brand assets. GD only — a bundled PHP extension, never a
 * require-dev package.
 */
class BrandAssetPipeline
{
    public const SLOTS = ['logo', 'logo_dark', 'mark', 'favicon', 'og_image'];

    public const DERIVATIVES = [
        'favicon-16' => [16, 16],
        'favicon-32' => [32, 32],
        'apple-touch-180' => [180, 180],
        'icon-192' => [192, 192],
        'icon-512' => [512, 512],
        'icon-maskable-512' => [512, 512],
        'og-1200x630' => [1200, 630],
        'logo-pdf' => [480, 480],
        'logo-email' => [320, 320],
    ];

    private const DISK = 'public';

    /** @throws ValidationException */
    public function assertSafe(UploadedFile $file, string $slot): void
    {
        $rule = new SafeImage($slot);
        $failed = null;

        $rule->validate($slot, $file, function (string $message) use (&$failed) {
            $failed = $message;
        });

        if ($failed !== null) {
            throw ValidationException::withMessages([$slot => $failed]);
        }
    }

    /** Content-addressed: a replaced asset can never be served from a stale cache. */
    public function store(UploadedFile $file, string $slot): string
    {
        $this->assertSafe($file, $slot);

        $bytes = (string) file_get_contents($file->getRealPath());
        $ext = SafeImage::extensionFor($bytes) ?? 'png';
        $path = 'brand/'.substr(sha1($bytes), 0, 8).'/'.$slot.'.'.$ext;

        Storage::disk(self::DISK)->put($path, $bytes);

        return $path;
    }

    /**
     * Renders every derivative for a slot. Never throws: with no raster source
     * it falls back to a rendered letter-mark on the primary colour.
     *
     * @return array<string,string> derivative key => stored path
     */
    public function derive(string $slot, ?string $path): array
    {
        $written = [];
        $source = $path !== null && Storage::disk(self::DISK)->exists($path)
            ? Storage::disk(self::DISK)->get($path)
            : null;

        foreach ($this->derivativesFor($slot) as $key) {
            [$w, $h] = self::DERIVATIVES[$key];

            $bytes = $source !== null ? $this->resize($source, $w, $h) : null;
            $bytes ??= $this->markPng($w, $h);

            if ($bytes === null) {
                continue;
            }

            $target = 'brand/derived/'.$this->stamp().'/'.$key.'.png';
            Storage::disk(self::DISK)->put($target, $bytes);
            $written[$key] = $target;
        }

        return $written;
    }

    /** Prunes orphaned content-addressed copies; the live path is always kept. */
    public function purge(string $slot): void
    {
        $keep = app(BrandManager::class)->raw()['brand'][$slot === 'og_image' ? 'og_image_path' : $slot.'_path'] ?? null;

        foreach (Storage::disk(self::DISK)->directories('brand') as $dir) {
            if (basename($dir) === 'derived') {
                continue;
            }

            foreach (Storage::disk(self::DISK)->files($dir) as $file) {
                if ($file !== $keep && str_starts_with(basename($file), $slot.'.')) {
                    Storage::disk(self::DISK)->delete($file);
                }
            }
        }
    }

    /** @return array<string,string> derivative key => public URL */
    public function iconManifest(): array
    {
        $out = [];
        $prefix = 'brand/derived/'.$this->stamp().'/';

        foreach (array_keys(self::DERIVATIVES) as $key) {
            $path = $prefix.$key.'.png';
            if (Storage::disk(self::DISK)->exists($path)) {
                $out[$key] = Storage::disk(self::DISK)->url($path);
            }
        }

        return $out;
    }

    public function derivativeBytes(string $key): ?string
    {
        $path = 'brand/derived/'.$this->stamp().'/'.$key.'.png';

        return Storage::disk(self::DISK)->exists($path)
            ? Storage::disk(self::DISK)->get($path)
            : null;
    }

    /** public/offline.html must stay a static file for service-worker precache. */
    public function publishOffline(): void
    {
        $html = view('brand.offline', ['brand' => app(BrandManager::class)->current()])->render();

        @file_put_contents(public_path('offline.html'), $html);
    }

    /** Raster of the brand initial on the primary colour. Never throws. */
    public function markPng(int $w, int $h): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $brand = app(BrandManager::class)->current();
        [$pr, $pg, $pb] = Color::toNormalisedRgb($brand->palette->primaryHex);
        [$fr, $fg, $fb] = Color::toNormalisedRgb($brand->palette->foregroundOn($brand->palette->primaryHex));

        try {
            $canvas = imagecreatetruecolor($w, $h);
            $bg = imagecolorallocate($canvas, (int) round($pr * 255), (int) round($pg * 255), (int) round($pb * 255));
            $fgColor = imagecolorallocate($canvas, (int) round($fr * 255), (int) round($fg * 255), (int) round($fb * 255));
            imagefilledrectangle($canvas, 0, 0, $w, $h, $bg);

            $letter = $brand->initial();
            if (preg_match('/^[A-Za-z0-9]$/', $letter) === 1) {
                $glyphW = imagefontwidth(5);
                $glyphH = imagefontheight(5);
                $tile = imagecreatetruecolor($glyphW, $glyphH);
                $tileBg = imagecolorallocate($tile, (int) round($pr * 255), (int) round($pg * 255), (int) round($pb * 255));
                $tileFg = imagecolorallocate($tile, (int) round($fr * 255), (int) round($fg * 255), (int) round($fb * 255));
                imagefilledrectangle($tile, 0, 0, $glyphW, $glyphH, $tileBg);
                imagestring($tile, 5, 0, 0, $letter, $tileFg);

                $side = (int) round(min($w, $h) * 0.55);
                $dstW = max(1, (int) round($side * ($glyphW / $glyphH)));
                imagecopyresampled(
                    $canvas, $tile,
                    (int) round(($w - $dstW) / 2), (int) round(($h - $side) / 2),
                    0, 0, $dstW, $side, $glyphW, $glyphH,
                );
                imagedestroy($tile);
            } else {
                imagefilledellipse($canvas, (int) ($w / 2), (int) ($h / 2), (int) ($w * 0.4), (int) ($h * 0.4), $fgColor);
            }

            ob_start();
            imagepng($canvas);
            $bytes = (string) ob_get_clean();
            imagedestroy($canvas);

            return $bytes;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int,string> */
    private function derivativesFor(string $slot): array
    {
        return match ($slot) {
            'favicon', 'mark' => ['favicon-16', 'favicon-32', 'apple-touch-180', 'icon-192', 'icon-512', 'icon-maskable-512'],
            'og_image' => ['og-1200x630'],
            default => ['logo-pdf', 'logo-email'],
        };
    }

    private function resize(string $bytes, int $w, int $h): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $src = @imagecreatefromstring($bytes);
            if ($src === false) {
                return null;
            }

            $dst = imagecreatetruecolor($w, $h);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefilledrectangle($dst, 0, 0, $w, $h, imagecolorallocatealpha($dst, 0, 0, 0, 127));

            $sw = imagesx($src);
            $sh = imagesy($src);
            $scale = min($w / $sw, $h / $sh);
            $dw = max(1, (int) round($sw * $scale));
            $dh = max(1, (int) round($sh * $scale));

            imagecopyresampled($dst, $src, (int) (($w - $dw) / 2), (int) (($h - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);

            ob_start();
            imagepng($dst);
            $out = (string) ob_get_clean();

            imagedestroy($src);
            imagedestroy($dst);

            return $out;
        } catch (\Throwable) {
            return null;
        }
    }

    private function stamp(): string
    {
        return app(BrandManager::class)->hash();
    }
}
