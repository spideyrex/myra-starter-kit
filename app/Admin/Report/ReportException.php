<?php

namespace App\Admin\Report;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** Always a 422. A rejected report never degrades into an unfiltered result. */
class ReportException extends HttpException
{
    public function __construct(public readonly string $key, ?string $message = null)
    {
        parent::__construct(422, $message ?? self::translate($key));
    }

    /** The key IS the message when there is no translator — Period is unit-tested outside Laravel. */
    private static function translate(string $key): string
    {
        return app()->bound('translator') ? (string) __($key) : $key;
    }

    public static function make(string $key): self
    {
        return new self($key);
    }
}
