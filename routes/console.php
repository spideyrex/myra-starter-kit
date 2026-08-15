<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');

// >>> MYRA v2.3 [D] START
Schedule::command('reports:dispatch')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
// <<< MYRA v2.3 [D] END
