<?php

namespace Tests\Feature\Security;

use App\Support\Myra;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * show() dereferenced $request->user()->id unguarded. The route sits behind
 * `auth`, so it was unreachable in practice — but any future change that
 * relaxes the middleware turns a 404 into a fatal, and a fatal leaks a stack
 * trace. The guard is asserted with the middleware stack removed.
 */
class InlineUploadGuardTest extends TestCase
{
    private const PATH = '1/01J0000000000000000000000A.png';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_show_answers_404_when_there_is_no_authenticated_user(): void
    {
        $this->withoutMiddleware();

        $this->get(route('admin.uploads.inline.show', ['path' => self::PATH]))
            ->assertNotFound();
    }

    /** The path shape is still rejected first, so no user lookup happens at all. */
    public function test_a_malformed_path_is_404_without_an_authenticated_user(): void
    {
        $this->withoutMiddleware();

        $this->get(Myra::adminPath('uploads/inline/inline/1/not-a-ulid.png'))->assertNotFound();
    }

    public function test_an_unauthenticated_request_never_reaches_the_controller(): void
    {
        $this->get(route('admin.uploads.inline.show', ['path' => self::PATH]))
            ->assertRedirect(route('login'));
    }
}
