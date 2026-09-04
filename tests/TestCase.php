<?php

namespace Tests;

use App\Models\Area;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createArea(array $overrides = []): Area
    {
        $suffix = uniqid();

        return Area::query()->create(array_merge([
            'name' => 'Reino '.$suffix,
            'slug' => 'reino-'.$suffix,
            'description' => 'Área de teste',
            'color' => '#c2410c',
            'emblem' => 'castle',
            'map_x' => 40,
            'map_y' => 45,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createAreaForTeacher(User $teacher, array $overrides = []): Area
    {
        $area = $this->createArea($overrides);
        $teacher->areas()->syncWithoutDetaching([$area->id]);

        return $area;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createClassForTeacher(User $teacher, array $overrides = []): SchoolClass
    {
        $area = $overrides['area'] ?? $this->createAreaForTeacher($teacher);
        unset($overrides['area']);

        return SchoolClass::query()->create(array_merge([
            'teacher_id' => $teacher->id,
            'area_id' => $area->id,
            'name' => 'Turma Teste',
            'year' => '2026',
            'score_mode' => 'up_from_zero',
            'team_grade_weight' => 1,
            'behavior_grade_weight' => 1,
        ], $overrides));
    }
}
