<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherClassShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_tab_does_not_render_other_tabs_markup(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertOk()
            ->assertSee('Novo aluno')
            ->assertSee('Alunos')
            ->assertSee('Lançar notas')
            ->assertSee('Chamada')
            ->assertSee('Arena')
            ->assertSee('Eventos')
            ->assertDontSee('Pedidos de personagem')
            ->assertDontSee('Evento da turma (item exclusivo)')
            ->assertDontSee('Nota de atividade')
            ->assertDontSee('Só o Presente gera Selos')
            ->assertDontSee('Arena de batalha');
    }

    public function test_events_tab_warns_when_the_scheduler_heartbeat_is_missing(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'eventos']))
            ->assertOk()
            ->assertSee('Agendador parado');
    }

    public function test_events_tab_hides_the_warning_after_a_fresh_tick(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->travelTo('2026-09-10 12:00:00');
        $this->artisan('game-events:tick')->assertSuccessful();

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'eventos']))
            ->assertOk()
            ->assertDontSee('Agendador parado');

        $this->travel(6)->minutes();

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'eventos']))
            ->assertOk()
            ->assertSee('Agendador parado');
    }

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertRedirectToRoute('login');
    }
}
