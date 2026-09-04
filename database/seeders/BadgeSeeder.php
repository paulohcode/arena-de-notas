<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['slug' => 'primeira-nota', 'name' => 'Primeira nota', 'description' => 'Recebeu a primeira nota na turma.', 'icon' => '🎯'],
            ['slug' => 'media-70', 'name' => 'Em ritmo', 'description' => 'Alcançou média 70 ou mais.', 'icon' => '🥉'],
            ['slug' => 'media-90', 'name' => 'Excelência', 'description' => 'Alcançou média 90 ou mais.', 'icon' => '🥇'],
            ['slug' => 'top-3', 'name' => 'Pódio', 'description' => 'Entrou no top 3 de jogadores.', 'icon' => '🏆'],
            ['slug' => 'guilda-primeiro', 'name' => 'Guilda campeã', 'description' => 'A guilda ficou em 1º no Hall.', 'icon' => '🏰'],
            ['slug' => 'recuperou', 'name' => 'Revanche', 'description' => 'Recuperou pontos depois de uma queda.', 'icon' => 'phoenix'],
            ['slug' => 'cinco-atividades', 'name' => 'Veterano', 'description' => 'Concluiu 5 atividades.', 'icon' => '📜'],
        ];

        foreach ($badges as $badge) {
            if ($badge['icon'] === 'phoenix') {
                $badge['icon'] = '🔥';
            }
            Badge::query()->updateOrCreate(['slug' => $badge['slug']], $badge);
        }
    }
}
