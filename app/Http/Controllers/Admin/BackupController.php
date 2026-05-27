<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): Response
    {
        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');
        $backups = [];

        $prefix = config('backup.backup.name') ?? config('app.name');
        $files = $disk->allFiles($prefix);

        foreach ($files as $file) {
            $backups[] = [
                'path' => $file,
                'name' => basename($file),
                'size' => round($disk->size($file) / 1048576, 2) . ' MB',
                'date' => date('Y-m-d H:i:s', $disk->lastModified($file)),
            ];
        }

        usort($backups, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return Inertia::render('Admin/Backups/Index', [
            'backups' => $backups,
        ]);
    }

    public function store(): RedirectResponse
    {
        Artisan::call('backup:run');

        return back()->with('success', 'Backup created successfully.');
    }

    public function download(string $path): StreamedResponse
    {
        $this->validateBackupPath($path);

        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');

        abort_unless($disk->exists($path), 404, 'Backup not found.');

        return $disk->download($path, basename($path), [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function destroy(string $path): RedirectResponse
    {
        $this->validateBackupPath($path);

        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');

        abort_unless($disk->exists($path), 404, 'Backup not found.');

        $disk->delete($path);

        return back()->with('success', 'Backup deleted successfully.');
    }

    /**
     * Validate that the given path is within the expected backup directory
     * and does not contain path traversal sequences.
     */
    private function validateBackupPath(string $path): void
    {
        // Reject path traversal sequences
        abort_if(
            str_contains($path, '..') || str_contains($path, "\0"),
            403,
            'Invalid backup path.'
        );

        // Ensure the path starts with the backup prefix directory
        $prefix = config('backup.backup.name') ?? config('app.name');
        abort_unless(
            str_starts_with($path, $prefix . '/'),
            403,
            'Invalid backup path.'
        );

        // Ensure it's a zip file
        abort_unless(
            str_ends_with(strtolower($path), '.zip'),
            403,
            'Invalid backup file type.'
        );
    }
}
