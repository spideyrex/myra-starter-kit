<?php

namespace App\Admin\Export;

/** Streaming row sink. Implementations must never buffer the whole result set. */
interface RowWriter
{
    public function open(): void;

    public function header(array $labels): void;

    public function row(array $values): void;

    public function flush(): void;

    public function close(): void;
}
