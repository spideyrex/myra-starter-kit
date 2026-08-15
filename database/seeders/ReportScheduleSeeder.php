<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * The scheduled-report template lives here, not in EmailTemplateSeeder, so the
 * reporting bundle owns its own seed data.
 */
class ReportScheduleSeeder extends Seeder
{
    public function run(): void
    {
        EmailTemplate::updateOrCreate(
            ['slug' => 'scheduled-report'],
            [
                'name' => 'Scheduled Report',
                'subject' => '{{report_name}} — {{period}}',
                'body_html' => '<h1>{{report_name}}</h1>'
                    .'<p>Covering {{period}}. Compared against {{comparison}}.</p>'
                    .'{{kpi_table}}'
                    .'<p><a href="{{view_url}}">Open the live report</a></p>',
                'variables' => ['report_name', 'period', 'comparison', 'kpi_table', 'view_url'],
            ],
        );
    }
}
