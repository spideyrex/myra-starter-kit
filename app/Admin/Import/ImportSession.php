<?php

namespace App\Admin\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The staged upload behind a token.
 *
 * preview() stages the file once; validate() and commit() re-read it from the
 * path derived from the token, never from the request. That closes the TOCTOU
 * hole where the browser re-uploaded at commit time and the committed file did
 * not have to be the previewed one.
 */
final class ImportSession
{
    private const PREFIX = 'myra.import.';

    private function __construct(
        public readonly string $token,
        public readonly int $userId,
        public readonly string $resource,
        public readonly string $path,
        public readonly array $headers,
    ) {}

    public static function stage(UploadedFile $file, string $resource, int $userId, array $headers): self
    {
        $token = (string) Str::ulid();
        $path = $file->storeAs("imports/{$userId}", "{$token}.csv", 'local');

        $session = new self($token, $userId, $resource, $path, $headers);

        Cache::put(self::PREFIX . $token, [
            'user_id' => $userId,
            'resource' => $resource,
            'path' => $path,
            'headers' => $headers,
            'failures' => 0,
        ], now()->addMinutes((int) config('myra.imports.token_ttl', 60)));

        return $session;
    }

    /** 422 when the token is unknown/expired, 403 when it belongs to somebody else. */
    public static function resolve(string $token, string $resource, int $userId): self
    {
        $data = Cache::get(self::PREFIX . $token);

        abort_if(! is_array($data), 422, __('transfer.import.tokenExpired'));
        abort_unless(($data['user_id'] ?? null) === $userId, 403);
        abort_unless(($data['resource'] ?? null) === $resource, 404);
        abort_unless(Storage::disk('local')->exists($data['path']), 422, __('transfer.import.tokenExpired'));

        return new self($token, $userId, $resource, $data['path'], $data['headers']);
    }

    public function fullPath(): string
    {
        return Storage::disk('local')->path($this->path);
    }

    public function failuresRelPath(): string
    {
        return "imports/{$this->userId}/{$this->token}-failures.csv";
    }

    public function failureCount(): int
    {
        return (int) (Cache::get(self::PREFIX . $this->token)['failures'] ?? 0);
    }

    public function addFailures(int $n): int
    {
        $data = Cache::get(self::PREFIX . $this->token, []);
        $data['failures'] = (int) ($data['failures'] ?? 0) + $n;
        Cache::put(self::PREFIX . $this->token, $data, now()->addMinutes((int) config('myra.imports.token_ttl', 60)));

        return $data['failures'];
    }
}
