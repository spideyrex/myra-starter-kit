<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (! function_exists('site_setting')) {
    function site_setting(string $key, mixed $default = null): mixed
    {
        return Cache::remember("site_setting.{$key}", 3600, function () use ($key, $default) {
            [$group, $name] = explode('.', $key, 2);

            $row = DB::table('settings')
                ->where('group', $group)
                ->where('name', $name)
                ->first();

            if (! $row) {
                return $default;
            }

            return json_decode($row->payload, true) ?? $default;
        });
    }
}
