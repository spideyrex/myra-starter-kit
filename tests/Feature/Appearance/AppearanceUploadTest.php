<?php

namespace Tests\Feature\Appearance;

use App\Appearance\Admin\AppearanceWriter;
use App\Support\Myra;
use App\Rules\SafeImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 7.7 — the background upload is magic-byte validated and lands somewhere an
 * UNAUTHENTICATED login-page visitor can actually fetch.
 */
class AppearanceUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    private function file(string $name, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'surfacetest');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function png(int $w = 32, int $h = 32): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    private function upload(UploadedFile $file, string $surface = 'auth')
    {
        return $this->post(route('admin.appearance.background.store', ['surface' => $surface]), ['image' => $file]);
    }

    private function storedPath(string $surface = 'auth'): ?string
    {
        $row = DB::table('settings')
            ->where('group', AppearanceWriter::GROUP)
            ->where('name', $surface.'_bg_image_path')
            ->value('payload');

        return $row === null ? null : json_decode((string) $row, true);
    }

    public function test_a_renamed_php_file_is_rejected(): void
    {
        $this->actingAsSuperAdmin();

        $this->upload($this->file('background.png', '<?php echo "pwned"; ?>'))
            ->assertSessionHasErrors('image');

        $this->assertNull($this->storedPath());
    }

    public function test_an_svg_is_rejected_by_mime_and_by_the_header_scan(): void
    {
        $this->actingAsSuperAdmin();

        $this->upload($this->file('background.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
            ->assertSessionHasErrors('image');

        $this->upload($this->file('background.webp', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>'))
            ->assertSessionHasErrors('image');

        $this->assertNull($this->storedPath());
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        $this->actingAsSuperAdmin();

        // The 'surface' cap is 4 MB and the byte check runs before the sniff.
        $oversized = "\x89PNG\r\n\x1a\n".str_repeat('A', 4 * 1024 * 1024 + 16);

        $this->upload($this->file('background.png', $oversized))->assertSessionHasErrors('image');

        $this->assertNull($this->storedPath());
    }

    public function test_a_zero_byte_file_is_rejected(): void
    {
        $this->actingAsSuperAdmin();

        $this->upload($this->file('background.png', ''))->assertSessionHasErrors('image');

        $this->assertNull($this->storedPath());
    }

    public function test_the_surface_slot_raises_the_byte_cap_above_the_default(): void
    {
        // Over the 2 MB default cap, under the 4 MB surface cap. The default slot
        // rejects it on size; the surface slot gets past size and only then finds
        // it is not a real image.
        $middling = "\x89PNG\r\n\x1a\n".str_repeat('A', 3 * 1024 * 1024);

        $onDefault = $this->fails($this->file('background.png', $middling), 'logo');
        $onSurface = $this->fails($this->file('background.png', $middling), 'surface');

        $this->assertNotNull($onDefault);
        $this->assertNotNull($onSurface);
        $this->assertNotSame($onDefault, $onSurface);

        $this->assertNull($this->fails($this->file('background.png', $this->png()), 'surface'));
    }

    private function fails(UploadedFile $file, string $slot): ?string
    {
        $message = null;
        (new SafeImage($slot))->validate($slot, $file, function (string $m) use (&$message) {
            $message = $m;
        });

        return $message;
    }

    public function test_an_accepted_upload_lands_on_the_public_disk(): void
    {
        $this->actingAsSuperAdmin();

        $this->upload($this->file('whatever.txt', $this->png()))->assertRedirect();

        $path = $this->storedPath();

        $this->assertIsString($path);
        $this->assertStringStartsWith('appearance/', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_the_upload_is_never_written_to_the_private_disk(): void
    {
        $this->actingAsSuperAdmin();

        $this->upload($this->file('background.png', $this->png()))->assertRedirect();

        $path = (string) $this->storedPath();

        // InlineUploadController writes to storage/app/private and its read route
        // 404s for a guest, so anything landing there is invisible to a visitor.
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertStringContainsString('/storage/'.$path, Storage::disk('public')->url($path));
        $this->assertSame('public', config('filesystems.disks.public.visibility'));
    }

    public function test_removing_the_image_clears_the_row(): void
    {
        $this->actingAsSuperAdmin();

        $this->upload($this->file('background.png', $this->png()))->assertRedirect();
        $this->assertIsString($this->storedPath());

        $this->delete(route('admin.appearance.background.destroy', ['surface' => 'auth']))->assertRedirect();

        $this->assertNull($this->storedPath());
    }

    public function test_an_unknown_surface_is_a_404(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(Myra::adminPath('appearance/background/nope'), ['image' => $this->file('a.png', $this->png())])
            ->assertNotFound();
    }

    public function test_the_upload_is_gated_by_brand_update(): void
    {
        $this->actingAsUser();

        $this->upload($this->file('background.png', $this->png()))->assertForbidden();
    }
}
