<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\EmailTemplate;
use App\Models\Page;
use App\Support\HtmlSanitizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One-off backfill that runs existing stored rich-text HTML through the
 * server-side HtmlSanitizer, so content created before sanitize-on-write is
 * brought up to the same safety baseline. Run with --dry-run first to preview.
 */
class SanitizeContentCommand extends Command
{
    protected $signature = 'content:sanitize {--dry-run : Report what would change without saving}';

    protected $description = 'Sanitize existing stored HTML (articles, pages, email templates) with the server-side HtmlSanitizer';

    /** [modelClass, html column] */
    private const TARGETS = [
        [Article::class, 'body_html'],
        [Page::class, 'body_html'],
        [EmailTemplate::class, 'body_html'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $grandScanned = 0;
        $grandChanged = 0;

        foreach (self::TARGETS as [$modelClass, $column]) {
            $query = $modelClass::query()->withoutGlobalScopes();

            if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                $query->withTrashed();
            }

            $scanned = 0;
            $changed = 0;

            $query->chunkById(200, function ($rows) use ($column, $dryRun, &$scanned, &$changed) {
                foreach ($rows as $row) {
                    $scanned++;
                    $clean = HtmlSanitizer::clean($row->{$column});

                    if ($clean !== (string) $row->{$column}) {
                        $changed++;
                        if (! $dryRun) {
                            $row->{$column} = $clean;
                            $row->saveQuietly(); // skip model events (no activity-log / updated_by churn)
                        }
                    }
                }
            });

            $this->components->twoColumnDetail(class_basename($modelClass), "{$changed} of {$scanned} " . ($dryRun ? 'would change' : 'sanitized'));
            $grandScanned += $scanned;
            $grandChanged += $changed;
        }

        $this->newLine();
        $this->components->info(($dryRun ? '[dry-run] ' : '') . "Done. {$grandChanged} of {$grandScanned} records " . ($dryRun ? 'would be updated.' : 'updated.'));

        return self::SUCCESS;
    }
}
