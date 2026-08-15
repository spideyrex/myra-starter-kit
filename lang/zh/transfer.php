<?php

return [
    'export' => [
        'tooManyRows' => '本次导出超过 :max 行的上限，请缩小筛选范围后重试。',
    ],

    'import' => [
        'badFile' => '仅支持导入 .csv 或 .txt 文件。',
        'noHeaders' => '无法将文件首行解析为列标题。',
        'tooManyColumns' => '该文件的列数超过 :max 列。',
        'tooManyRows' => '该文件的行数超过 :max 行。',
        'tokenExpired' => '本次上传已过期，请重新上传文件。',
        'mappingRequired' => '导入前必须映射所有必填列。',
        'unknownColumn' => '映射中包含此导入未声明的列。',
        'rowForbidden' => '您无权导入该记录。',
    ],
];
