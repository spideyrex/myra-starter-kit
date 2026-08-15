<?php

namespace App\Admin\Export;

use App\Support\Csv;

final class CsvRowWriter implements RowWriter
{
    /** @var resource|null */
    private $handle = null;

    public function open(): void
    {
        $this->handle = fopen('php://output', 'w');
    }

    public function header(array $labels): void
    {
        $this->row($labels);
    }

    public function row(array $values): void
    {
        fputcsv($this->handle, Csv::row($values));
    }

    public function flush(): void
    {
        if (is_resource($this->handle)) {
            fflush($this->handle);
        }

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        @flush();
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->handle = null;
    }
}
