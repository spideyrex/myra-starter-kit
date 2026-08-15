<?php

namespace App\Policies;

use App\Models\ReportSchedule;
use App\Models\User;

class ReportSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reports.schedule');
    }

    /** Owner, or a teammate of a team-scoped schedule. */
    public function view(User $user, ReportSchedule $schedule): bool
    {
        if (! $user->can('reports.schedule')) {
            return false;
        }

        if ($schedule->isOwnedBy($user)) {
            return true;
        }

        return $schedule->team_id !== null
            && (int) $schedule->team_id === (int) $user->current_team_id
            && $user->teams()->whereKey($schedule->team_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('reports.schedule');
    }

    /** A teammate may read a team schedule but only its author may change it. */
    public function update(User $user, ReportSchedule $schedule): bool
    {
        return $user->can('reports.schedule') && $schedule->isOwnedBy($user);
    }

    public function delete(User $user, ReportSchedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    /**
     * A test send runs as, and mails, the actor only (SendScheduledReport's
     * $testForUserId), so read access is enough — it can neither reach the
     * schedule's recipient list nor read the owner's scoped rows.
     */
    public function test(User $user, ReportSchedule $schedule): bool
    {
        return $this->view($user, $schedule);
    }
}
