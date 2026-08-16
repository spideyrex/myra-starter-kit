<?php

namespace App\Console\Commands\Myra;

use App\Admin\Tenancy\BaselineProbe;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Writes tests/fixtures/tenancy-baseline.json — the compiled SQL of the
 * canonical query per model, as guest, as a non-super member, and as a
 * super-admin.
 *
 * Run this BEFORE any tenancy code touches a model. Regenerating it afterwards
 * would make DisabledPathIsNoOpTest assert against the very change it exists to
 * refute.
 */
class TenancyBaselineCommand extends Command
{
    protected $signature = 'myra:tenancy-baseline
        {--member= : Id of a non-super user to capture the "member" case as}
        {--super=  : Id of a super-admin user to capture the "super" case as}
        {--path=   : Output path (default tests/fixtures/tenancy-baseline.json)}';

    protected $description = 'Capture the pre-tenancy query baseline used by DisabledPathIsNoOpTest';

    public function handle(): int
    {
        $super = (string) config('shield.super_admin_role', 'super-admin');

        $member = $this->option('member')
            ? User::find($this->option('member'))
            : User::query()->whereDoesntHave('roles', fn ($q) => $q->where('name', $super))->first();

        $superUser = $this->option('super')
            ? User::find($this->option('super'))
            : User::query()->whereHas('roles', fn ($q) => $q->where('name', $super))->first();

        if ($member === null || $superUser === null) {
            $this->components->error('Need one non-super user and one super-admin to capture the baseline.');

            return self::FAILURE;
        }

        $previous = Auth::user();

        try {
            Auth::forgetUser();
            $guest = fn () => null;

            $data = [];

            foreach (BaselineProbe::MODELS as $modelClass) {
                $row = [];

                Auth::forgetUser();
                $row['guest'] = BaselineProbe::query($modelClass, $guest());

                Auth::setUser($member);
                $row['member'] = BaselineProbe::query($modelClass, $member);

                Auth::setUser($superUser);
                $row['super'] = BaselineProbe::query($modelClass, $superUser);

                $row['scopes'] = BaselineProbe::scopeKeys($modelClass);
                $data[$modelClass] = $row;
            }
        } finally {
            $previous === null ? Auth::forgetUser() : Auth::setUser($previous);
        }

        $path = (string) ($this->option('path') ?: base_path('tests/fixtures/tenancy-baseline.json'));

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, BaselineProbe::encode($data));

        $this->components->info('Baseline written to '.$path);

        return self::SUCCESS;
    }
}
