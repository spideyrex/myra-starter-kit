<?php

namespace App\Homepage\Sections;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The draft-preview slot. One per session, 15 minutes.
 *
 * The preview iframe loads the REAL public homepage — there is no bespoke
 * preview renderer — so this is the only coupling between the editor and the
 * public render path. Mirrors TemplateRegistry::mayPreview() exactly.
 */
final class PreviewSlot
{
    public const KEY = 'myra.pagebuilder.preview';

    public const TTL = 900;

    /**
     * The stored draft, or null. Never throws, never persists anything.
     *
     * @return array<int,mixed>|null
     */
    public static function pull(Request $request): ?array
    {
        $token = $request->query('preview');

        if (! is_string($token) || $token === '') {
            return null;
        }

        if (! self::mayPreview($request)) {
            return null;
        }

        $slot = $request->hasSession() ? $request->session()->get(self::KEY) : null;

        if (! is_array($slot)) {
            return null;
        }

        $stored = $slot['token'] ?? null;
        $expiresAt = $slot['expires_at'] ?? 0;

        if (! is_string($stored) || $stored === '' || ! hash_equals($stored, $token)) {
            return null;
        }

        if (! is_int($expiresAt) || $expiresAt <= time()) {
            return null;
        }

        return is_array($slot['blocks'] ?? null) ? $slot['blocks'] : null;
    }

    /** Writes the slot and returns its token. One slot per session — a new call overwrites. */
    public static function store(Request $request, array $blocks): string
    {
        $token = (string) Str::ulid();

        $request->session()->put(self::KEY, [
            'token' => $token,
            'blocks' => $blocks,
            'expires_at' => time() + self::TTL,
        ]);

        return $token;
    }

    private static function mayPreview(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof Authorizable && $user->can('settings.edit');
    }
}
