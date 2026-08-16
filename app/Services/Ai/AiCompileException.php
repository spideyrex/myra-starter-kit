<?php

namespace App\Services\Ai;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Always a 422. Model output that cannot be turned into a validated structure is
 * refused outright — there is no partial or best-effort result.
 */
class AiCompileException extends HttpException
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
