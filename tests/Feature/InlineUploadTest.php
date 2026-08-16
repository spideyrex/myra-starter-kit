<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Myra;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InlineUploadTest extends TestCase
{
    /** A real 1x1 PNG — getimagesize() must accept these bytes. */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function userWith(array $permissions): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function actingAsUserWith(array $permissions): User
    {
        $user = $this->userWith($permissions);
        $this->actingAs($user);

        return $user;
    }

    private function pngFile(string $name = 'shot.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG_1X1));
    }

    /** The editor posts XHR, so validation must answer 422 rather than redirect. */
    private function upload(UploadedFile $file)
    {
        return $this->post(route('admin.uploads.inline'), ['file' => $file], ['Accept' => 'application/json']);
    }

    /**
     * Store a real image as $owner and return the relative storage path.
     *
     * The route segment is itself named "inline", so the URL reads
     * /admin/uploads/inline/inline/{id}/{ulid}.png. Searching for the first
     * "inline/" hits the ROUTE segment, so strip the exact route prefix instead.
     */
    private function storeAs(User $owner): string
    {
        $this->actingAs($owner);
        $response = $this->upload($this->pngFile());
        $response->assertOk();

        $sentinel = '__STORAGE_PATH__';
        $prefix = Str::before(route('admin.uploads.inline.show', ['path' => $sentinel]), $sentinel);

        $url = (string) $response->json('url');
        $this->assertStringStartsWith($prefix, $url);

        return urldecode(Str::after($url, $prefix));
    }

    public function test_a_valid_image_is_stored_on_the_private_disk(): void
    {
        $user = $this->actingAsUserWith(['media.create', 'media.view']);

        $response = $this->upload($this->pngFile());

        $response->assertOk();
        // One 'inline' segment, not two: the route re-adds the storage prefix.
        $url = (string) $response->json('url');
        $this->assertStringContainsString('/uploads/inline/'.$user->id.'/', $url);
        $this->assertStringNotContainsString('/uploads/inline/inline/', $url);
        $this->assertCount(1, Storage::disk('local')->files('inline/'.$user->id));
    }

    public function test_a_file_that_only_claims_to_be_an_image_is_rejected(): void
    {
        $this->actingAsUserWith(['media.create']);

        // Passes `mimes:` (the client-supplied type is trusted by the rule) but
        // carries no image magic bytes.
        $this->upload(UploadedFile::fake()->create('evil.png', 4, 'image/png'))->assertStatus(422);

        $this->assertEmpty(Storage::disk('local')->allFiles('inline'));
    }

    public function test_a_text_file_named_png_is_rejected(): void
    {
        $this->actingAsUserWith(['media.create']);

        $this->upload(UploadedFile::fake()->createWithContent('evil.png', 'not an image at all'))->assertStatus(422);

        $this->assertEmpty(Storage::disk('local')->allFiles('inline'));
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        $this->actingAsUserWith(['media.create']);

        $this->upload(UploadedFile::fake()->create('huge.png', 6 * 1024, 'image/png'))->assertStatus(422);

        $this->assertEmpty(Storage::disk('local')->allFiles('inline'));
    }

    public function test_upload_requires_the_media_create_permission(): void
    {
        $this->actingAsUserWith(['media.view']);

        $this->upload($this->pngFile())->assertForbidden();
    }

    public function test_another_user_gets_404_not_403(): void
    {
        $path = $this->storeAs($this->userWith(['media.create']));

        $this->actingAs($this->userWith(['media.create']));

        // 404, never 403 — a 403 would confirm the file exists.
        $this->get(route('admin.uploads.inline.show', ['path' => $path]))->assertNotFound();
    }

    /** The returned URL must round-trip to the exact key on the private disk. */
    public function test_the_returned_url_round_trips_to_the_stored_path(): void
    {
        $owner = $this->userWith(['media.create']);

        $path = $this->storeAs($owner);

        // The URL carries the path without its storage prefix...
        $this->assertMatchesRegularExpression(
            '#^'.$owner->id.'/[0-9A-HJKMNP-TV-Z]{26}\.png$#',
            $path,
        );
        // ...and still resolves to the exact key on the private disk.
        $this->assertTrue(Storage::disk('local')->exists('inline/'.$path));
    }

    public function test_the_owner_may_read_their_own_upload(): void
    {
        $owner = $this->userWith(['media.create']);
        $path = $this->storeAs($owner);

        $this->get(route('admin.uploads.inline.show', ['path' => $path]))->assertOk();
    }

    public function test_a_media_viewer_may_read_another_users_upload(): void
    {
        $owner = $this->userWith(['media.create']);
        $path = $this->storeAs($owner);

        $viewer = $this->actingAsUserWith(['media.view']);
        $this->assertNotSame($owner->id, $viewer->id);

        $this->get(route('admin.uploads.inline.show', ['path' => $path]))->assertOk();
    }

    public function test_the_response_carries_hardening_headers(): void
    {
        $path = $this->storeAs($this->userWith(['media.create']));

        $response = $this->get(route('admin.uploads.inline.show', ['path' => $path]));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public static function badPathProvider(): array
    {
        return [
            'not a ulid' => ['inline/1/hello.png'],
            'wrong extension' => ['inline/1/01J0000000000000000000000A.svg'],
            'nested deeper' => ['inline/1/sub/01J0000000000000000000000A.png'],
            'non numeric owner' => ['inline/abc/01J0000000000000000000000A.png'],
        ];
    }

    /** @dataProvider badPathProvider */
    public function test_a_malformed_path_is_404(string $path): void
    {
        $this->actingAsUserWith(['media.view']);

        $this->get(Myra::adminPath('uploads/inline/').$path)->assertNotFound();
    }
}
