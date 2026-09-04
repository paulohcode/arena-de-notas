<?php

namespace Tests\Unit;

use App\Support\ArenaUrl;
use PHPUnit\Framework\TestCase;

class ArenaUrlTest extends TestCase
{
    public function test_keeps_paths_that_already_start_with_a_slash(): void
    {
        $this->assertSame('/aluno/arena', ArenaUrl::toLocalPath('/aluno/arena'));
    }

    public function test_strips_foreign_hosts_from_absolute_urls(): void
    {
        $this->assertSame(
            '/aluno/arena/duelos/4?tab=log#topo',
            ArenaUrl::toLocalPath('http://localhost:8000/aluno/arena/duelos/4?tab=log#topo'),
        );
    }

    public function test_returns_null_for_empty_values(): void
    {
        $this->assertNull(ArenaUrl::toLocalPath(null));
        $this->assertNull(ArenaUrl::toLocalPath(''));
    }

    public function test_localizes_url_keys_inside_a_payload(): void
    {
        $payload = ArenaUrl::localizePayload([
            'url' => 'https://outro.exemplo/aluno/arena',
            'duel_id' => 9,
        ]);

        $this->assertSame('/aluno/arena', $payload['url']);
        $this->assertSame(9, $payload['duel_id']);
    }
}
