<?php

namespace App\Admin\QueryBuilder;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Always a 422. Myra fails closed: a rejected tree never degrades into an
 * unfiltered result set.
 */
class QueryBuilderException extends HttpException
{
    public function __construct(public readonly string $key, ?string $message = null)
    {
        parent::__construct(422, $message ?? __($key));
    }

    public static function make(string $key): self
    {
        return new self($key);
    }
}
