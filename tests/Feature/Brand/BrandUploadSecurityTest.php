<?php

namespace Tests\Feature\Brand;

use App\Brand\BrandAssetPipeline;
use App\Rules\SafeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandUploadSecurityTest extends TestCase
{
    use SeedsBrand;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedBrand();
    }

    private function upload(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'brandtest');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function png(int $w = 24, int $h = 24): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    private function fails(UploadedFile $file, string $slot = 'logo'): ?string
    {
        $message = null;
        (new SafeImage($slot))->validate($slot, $file, function (string $m) use (&$message) {
            $message = $m;
        });

        return $message;
    }

    public function test_svg_is_rejected_even_when_named_png(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->assertNotNull($this->fails($this->upload('logo.png', $svg)));
    }

    public function test_ico_is_accepted(): void
    {
        // A 1x1 ICO header is enough: the magic bytes are what classify it.
        $ico = "\x00\x00\x01\x00\x01\x00\x10\x10\x00\x00\x01\x00\x20\x00".str_repeat("\x00", 32);

        $this->assertNull($this->fails($this->upload('favicon.ico', $ico), 'favicon'));
    }

    public function test_a_png_renamed_to_jpg_is_classified_by_its_magic_bytes(): void
    {
        $bytes = $this->png();

        $this->assertNull($this->fails($this->upload('logo.jpg', $bytes)));
        $this->assertSame('png', SafeImage::extensionFor($bytes));
    }

    public function test_an_oversized_image_is_refused(): void
    {
        $bytes = $this->png(2000, 2000);

        $this->assertNotNull($this->fails($this->upload('favicon.png', $bytes), 'favicon'));
    }

    public function test_a_text_file_named_png_is_refused(): void
    {
        $this->assertNotNull($this->fails($this->upload('logo.png', 'not an image at all')));
    }

    public function test_stored_assets_are_content_addressed(): void
    {
        $bytes = $this->png();
        $path = app(BrandAssetPipeline::class)->store($this->upload('whatever.txt', $bytes), 'logo');

        $this->assertSame('brand/'.substr(sha1($bytes), 0, 8).'/logo.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_the_appearance_tab_now_refuses_svg_too(): void
    {
        $this->actingAsSuperAdmin();

        $svg = $this->upload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->post(route('admin.settings.update-appearance'), ['logo' => $svg])
            ->assertSessionHasErrors('logo');
    }
}
