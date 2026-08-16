<?php

namespace App\Services\Ai\Compiler;

use App\Services\Ai\AiCompileException;

/**
 * A tripwire so a provider that starts emitting SQL trips a loud 422 rather than
 * a silent parse failure. RuleTree::parse() is the actual defence.
 */
final class AiOutputGuard
{
    private const PATTERNS = [
        '/\bselect\b/i',
        '/\binsert\b/i',
        '/\bupdate\b/i',
        '/\bdelete\b/i',
        '/\bdrop\b/i',
        '/\btruncate\b/i',
        '/\bunion\b/i',
        '/--/',
        '#/\*#',
        '/;\s+\w/',
    ];

    /** @throws AiCompileException */
    public static function assertNoSql(string $raw): void
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $raw) === 1) {
                throw AiCompileException::make('assistant.errors.sqlDetected');
            }
        }
    }
}
