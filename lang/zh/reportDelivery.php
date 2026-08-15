<?php

return [
    'pdf' => [
        'actor' => '生成者',
        'bucket' => '分组方式',
        'change' => '变化',
        'data' => '数据',
        'dimension' => '维度',
        'filters' => '筛选条件',
        'generatedBy' => '由 :name 于 :at 生成',
        'noFilters' => '未应用筛选条件',
        'periodSentence' => '涵盖 :from 至 :to（:tz）。',
        'comparisonSentence' => '与 :from 至 :to 对比。',
        'previous' => '上一期',
        'provenance' => '生成方式',
        'truncated' => '显示 :total 个分组中的前 :shown 个。',
    ],

    'mail' => [
        'subject' => ':report — :period',
    ],

    'notifications' => [
        'pausedTitle' => '一个定时报表已暂停',
        'pausedBody' => '“:name”已停止投递并被暂停。请打开查看原因。',
    ],

    'errors' => [
        'ownerLostAccess' => '计划的所有者已无权访问此报表。',
        'pausedAfterFailures' => '因多次投递失败而暂停。',
        'tooManySchedules' => '您已达到报表计划的数量上限。',
        'noRecipients' => '以您当前的权限，所选收件人均无法接收邮件。',
        'unknownReport' => '该报表不存在。',
        'badTimezone' => '无法识别该时区。',
    ],
];
