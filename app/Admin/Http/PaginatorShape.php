<?php

namespace App\Admin\Http;

use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Normalises a CursorPaginator into the shape DataTable already consumes.
 *
 * `meta.mode` is stamped ONLY here: an absent `mode` means length-aware, so every
 * existing controller response stays byte-identical and needs no edit.
 */
final class PaginatorShape
{
    public static function cursor(CursorPaginator $p): array
    {
        return [
            'data' => $p->items(),
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => $p->previousPageUrl(),
                'next' => $p->nextPageUrl(),
            ],
            'meta' => [
                'mode' => 'cursor',
                'path' => $p->path(),
                'per_page' => $p->perPage(),
                'next_cursor' => $p->nextCursor()?->encode(),
                'prev_cursor' => $p->previousCursor()?->encode(),
            ],
        ];
    }
}
