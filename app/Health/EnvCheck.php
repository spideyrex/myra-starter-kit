<?php

namespace App\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class EnvCheck extends Check
{
    public function run(): Result
    {
        $warnings = [];
        $failures = [];

        if (empty(config('app.key'))) {
            $failures[] = 'APP_KEY is not set';
        }

        if (app()->isProduction()) {
            if (config('app.url') === 'http://localhost') {
                $warnings[] = 'APP_URL is http://localhost in production';
            }

            if (config('mail.default') === 'log') {
                $warnings[] = 'MAIL_MAILER is set to log in production';
            }

            if (config('app.debug')) {
                $failures[] = 'APP_DEBUG is true in production';
            }
        }

        if (count($failures) > 0) {
            return Result::make()
                ->failed(implode('; ', array_merge($failures, $warnings)));
        }

        if (count($warnings) > 0) {
            return Result::make()
                ->warning(implode('; ', $warnings));
        }

        return Result::make()
            ->ok('All environment variables are properly configured');
    }
}
