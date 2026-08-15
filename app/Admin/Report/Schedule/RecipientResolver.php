<?php

namespace App\Admin\Report\Schedule;

use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Drops any recipient the owner cannot address. An external address requires
 * `reports.schedule.external`; without it only users the owner can already see
 * survive. An arbitrary email address on a recurring, owner-scoped report is a
 * data-exfiltration vector, so the whitelist is applied here, not in the UI.
 */
final class RecipientResolver
{
    /** @return array<int, array{email: string, name: string, locale: string}> */
    public static function resolve(ReportSchedule $schedule, User $owner): array
    {
        $max = (int) config('myra.report_schedules.max_recipients', 25);
        $allowsExternal = $owner->can('reports.schedule.external');

        $userIds = [];
        $external = [];

        foreach ((array) ($schedule->recipients ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['type'] ?? null) === 'user' && isset($entry['id'])) {
                $userIds[(int) $entry['id']] = true;

                continue;
            }

            if (($entry['type'] ?? null) === 'email' && $allowsExternal) {
                $address = strtolower(trim((string) ($entry['address'] ?? '')));

                if (filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $external[$address] = true;
                }
            }
        }

        $out = [];

        if ($userIds !== []) {
            $users = self::addressable($owner)
                ->whereKey(array_keys($userIds))
                ->get(['id', 'name', 'email']);

            foreach ($users as $user) {
                $out[strtolower($user->email)] = [
                    'email' => $user->email,
                    'name' => (string) $user->name,
                    'locale' => self::localeOf($user),
                ];
            }
        }

        foreach (array_keys($external) as $address) {
            $out[$address] ??= ['email' => $address, 'name' => $address, 'locale' => self::localeOf($owner)];
        }

        return array_slice(array_values($out), 0, max(1, $max));
    }

    /**
     * The pickable list, pruned by the SAME rule resolve() applies — so nobody
     * discovers on Monday that half their list was silently dropped.
     *
     * @return array<int, array{id:int, name:string, email:string}>
     */
    public static function addressableFor(User $owner, int $limit = 100): array
    {
        return self::addressable($owner)
            ->orderBy('name')
            ->limit(max(1, $limit))
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => (int) $u->id, 'name' => (string) $u->name, 'email' => (string) $u->email])
            ->all();
    }

    /** Users the owner may already see: themself, teammates, and their own records. */
    private static function addressable(User $owner): Builder
    {
        $query = User::query()->whereNotNull('email');

        if ($owner->hasRole((string) config('shield.super_admin_role', 'super-admin'))) {
            return $query;
        }

        $teamIds = $owner->teams()->select('teams.id');

        return $query->where(function (Builder $q) use ($owner, $teamIds) {
            $q->whereKey($owner->getKey())
                ->orWhere('created_by', $owner->getKey())
                ->orWhereHas('teams', fn (Builder $t) => $t->whereIn('teams.id', $teamIds));
        });
    }

    public static function localeOf(User $user): string
    {
        $locale = $user->getAttribute('locale');

        return is_string($locale) && $locale !== '' ? $locale : (string) config('app.locale', 'en');
    }
}
