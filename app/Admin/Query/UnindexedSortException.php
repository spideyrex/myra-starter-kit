<?php

namespace App\Admin\Query;

use RuntimeException;

/** Thrown outside production when a whitelisted sort column has no backing index. */
final class UnindexedSortException extends RuntimeException
{
    public static function for(string $table, string $column): self
    {
        return new self(sprintf(
            'Sorting [%s.%s] has no index whose leading column is [%s]. '
            . 'Add one: $table->index([\'%s\', \'id\'], \'%s_%s_id_idx\');',
            $table, $column, $column, $column, $table, $column,
        ));
    }
}
