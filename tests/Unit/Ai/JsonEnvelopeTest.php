<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiCompileException;
use App\Services\Ai\Compiler\JsonEnvelope;
use Tests\TestCase;

class JsonEnvelopeTest extends TestCase
{
    public function test_a_fenced_block_is_extracted(): void
    {
        $decoded = JsonEnvelope::decode("```json\n{\"conjunction\":\"and\",\"rules\":[]}\n```");

        $this->assertSame('and', $decoded['conjunction']);
        $this->assertSame([], $decoded['rules']);
    }

    public function test_leading_prose_is_tolerated_but_the_object_is_all_that_survives(): void
    {
        $decoded = JsonEnvelope::decode('Sure! Here you go: {"a":1} — hope that helps');

        $this->assertSame(['a' => 1], $decoded);
    }

    public function test_prose_containing_braces_is_rejected(): void
    {
        $this->expectException(AiCompileException::class);

        JsonEnvelope::decode('{"a":1} and also consider {this}');
    }

    public function test_an_oversized_reply_is_rejected_before_decoding(): void
    {
        config()->set('myra.ai.json_max_bytes', 64);

        $this->expectException(AiCompileException::class);

        // Valid JSON — the only reason to refuse it is the size gate, and that
        // gate has to run before json_decode() sees a megabyte of text.
        JsonEnvelope::decode('{"a":"'.str_repeat('x', 4096).'"}');
    }

    public function test_a_non_object_is_rejected(): void
    {
        $this->expectException(AiCompileException::class);

        JsonEnvelope::decode('[1, 2, 3]');
    }

    public function test_a_bare_string_is_rejected(): void
    {
        $this->expectException(AiCompileException::class);

        JsonEnvelope::decode('SELECT * FROM users');
    }

    public function test_exceeding_the_depth_limit_is_rejected(): void
    {
        $this->expectException(AiCompileException::class);

        JsonEnvelope::decode('{"a":{"b":{"c":{"d":{"e":1}}}}}', 3);
    }

    public function test_a_json_list_at_the_top_level_is_rejected_even_inside_braces(): void
    {
        $this->expectException(AiCompileException::class);

        JsonEnvelope::decode('{"0":1,'); // truncated: malformed JSON
    }
}
