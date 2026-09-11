<?php

namespace Tests\Feature;

use App\Models\Duel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArenaRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_read_arena_rules(): void
    {
        $this->get(route('arena.rules'))
            ->assertOk()
            ->assertSee('Regras da arena')
            ->assertSee('A classe é o')
            ->assertSee('estilo')
            ->assertSee('A nota é o')
            ->assertSee('poder')
            ->assertSee('não substitui a prova')
            ->assertSee('Linha de frente')
            ->assertSee('Guerreiro')
            ->assertSee('Mago')
            ->assertSee('Clérigo')
            ->assertSee('não altera a média')
            ->assertSee('não dá XP')
            ->assertSee('+'.Duel::GLORY_WIN.' Glória')
            ->assertDontSee('HP 100')
            ->assertDontSee('até +30%');
    }

    public function test_student_without_character_class_can_read_arena_rules(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => null,
            'must_change_password' => false,
        ]);

        $this->actingAs($student)
            ->get(route('arena.rules'))
            ->assertOk()
            ->assertSee('Três partes do lutador são iguais');
    }
}
