<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function switch(Request $request, Team $team)
    {
        $user = $request->user();

        // Ensure user belongs to this team
        if (!$user->teams()->where('teams.id', $team->id)->exists()) {
            abort(403, 'You do not belong to this team.');
        }

        $user->update(['current_team_id' => $team->id]);

        return back()->with('success', "Switched to {$team->name}.");
    }
}
