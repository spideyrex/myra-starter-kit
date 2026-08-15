<?php

return [
    'export' => [
        'tooManyRows' => 'This export is larger than the :max row limit. Narrow your filters and try again.',
    ],

    'import' => [
        'badFile' => 'Only .csv or .txt files can be imported.',
        'noHeaders' => 'The first row of the file could not be read as column headers.',
        'tooManyColumns' => 'The file has more than :max columns.',
        'tooManyRows' => 'The file has more than :max rows.',
        'tokenExpired' => 'This upload has expired. Upload the file again.',
        'mappingRequired' => 'Every required column must be mapped before importing.',
        'unknownColumn' => 'The mapping refers to a column this import does not declare.',
        'rowForbidden' => 'You are not allowed to import this record.',
    ],
];
