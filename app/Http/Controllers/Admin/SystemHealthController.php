<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Enums\Status;
use Spatie\Health\ResultStores\ResultStore;

class SystemHealthController extends Controller
{
    public function index(): Response
    {
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
            'database' => config('database.default'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_driver' => config('queue.default'),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'disk_free' => disk_free_space('/') ? round(disk_free_space('/') / 1073741824, 2) . ' GB' : 'N/A',
            'memory_usage' => round(memory_get_usage(true) / 1048576, 2) . ' MB',
        ];

        // Run health checks and get results
        $healthChecks = [];
        try {
            \Illuminate\Support\Facades\Artisan::call(RunHealthChecksCommand::class);
            $results = app(ResultStore::class)->latestResults();

            if ($results) {
                $healthChecks = $results->storedCheckResults->map(fn ($r) => [
                    'name' => $r->label,
                    'status' => is_object($r->status) ? $r->status->value : $r->status,
                    'message' => $r->shortSummary ?? '',
                ])->values()->all();
            }
        } catch (\Throwable $e) {
            $healthChecks = [['name' => 'Health System', 'status' => 'failed', 'message' => $e->getMessage()]];
        }

        // DB size
        $dbSize = 'N/A';
        try {
            $dbName = config('database.connections.mysql.database');
            $result = DB::selectOne("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = ?", [$dbName]);
            $dbSize = ($result->size ?? '0') . ' MB';
        } catch (\Throwable) {}

        $systemInfo['db_size'] = $dbSize;

        return Inertia::render('Admin/SystemHealth', [
            'systemInfo' => $systemInfo,
            'healthChecks' => $healthChecks,
        ]);
    }
}
