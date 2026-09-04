<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArenaTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_visible(): void
    {
        $this->get('/login')->assertOk()->assertSee('Entrar na arena');
    }

    public function test_teacher_can_open_dashboard(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->get('/professor')
            ->assertOk()
            ->assertSee('Painel do professor');
    }

    public function test_student_without_class_sees_empty_state(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => 'guerreiro',
        ]);

        $this->actingAs($student)
            ->get('/aluno')
            ->assertOk()
            ->assertSee('não está em nenhuma turma');
    }
}
