<?php

return [
    'pdf' => [
        'actor' => 'Dijana oleh',
        'bucket' => 'Dikumpulkan mengikut',
        'change' => 'Perubahan',
        'data' => 'Data',
        'dimension' => 'Dimensi',
        'filters' => 'Penapis',
        'generatedBy' => 'Dijana oleh :name pada :at',
        'noFilters' => 'Tiada penapis digunakan',
        'periodSentence' => 'Meliputi :from hingga :to (:tz).',
        'comparisonSentence' => 'Dibandingkan dengan :from hingga :to.',
        'previous' => 'Sebelumnya',
        'provenance' => 'Bagaimana ia dihasilkan',
        'truncated' => 'Menunjukkan :shown teratas daripada :total kumpulan.',
    ],

    'mail' => [
        'subject' => ':report — :period',
    ],

    'notifications' => [
        'pausedTitle' => 'Satu laporan berjadual telah dijeda',
        'pausedBody' => '":name" berhenti dihantar dan telah dijeda. Buka untuk melihat sebabnya.',
    ],

    'errors' => [
        'ownerLostAccess' => 'Pemilik jadual tidak lagi mempunyai akses kepada laporan ini.',
        'pausedAfterFailures' => 'Dijeda selepas kegagalan penghantaran berulang.',
        'tooManySchedules' => 'Anda telah mencapai had jadual untuk laporan.',
        'noRecipients' => 'Tiada penerima yang dipilih boleh dihubungi dengan kebenaran anda sekarang.',
        'unknownReport' => 'Laporan itu tidak wujud.',
        'badTimezone' => 'Zon waktu itu tidak dikenali.',
        'missingTemplate' => 'Templat e-mel ":slug" tiada. Jalankan `php artisan db:seed --class=ReportScheduleSeeder` untuk memulihkannya.',
    ],
];
