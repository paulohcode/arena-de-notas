<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function store(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'string', 'max:20'],
            'emblem' => ['required', 'in:'.implode(',', array_keys(Team::EMBLEMS))],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        $memberIds = $this->allowedMemberIds($schoolClass, $data['members'] ?? []);
        if ($this->takenMemberIds($schoolClass, $memberIds) !== []) {
            return back()
                ->withInput(['tab' => 'guildas'])
                ->withErrors(['members' => 'Um aluno só pode estar em uma guilda desta turma.']);
        }

        $team = $schoolClass->teams()->create([
            'name' => $data['name'],
            'color' => $data['color'],
            'emblem' => $data['emblem'],
        ]);

        $team->members()->sync($memberIds);

        return back()
            ->withInput(['tab' => 'guildas'])
            ->with('success', 'Guilda criada.');
    }

    public function update(Request $request, SchoolClass $schoolClass, Team $team): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($team->class_id === $schoolClass->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'string', 'max:20'],
            'emblem' => ['required', 'in:'.implode(',', array_keys(Team::EMBLEMS))],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);

        $memberIds = $this->allowedMemberIds($schoolClass, $data['members'] ?? []);
        if ($this->takenMemberIds($schoolClass, $memberIds, $team->id) !== []) {
            return back()
                ->withInput(['tab' => 'guildas'])
                ->withErrors(['members' => 'Um aluno só pode estar em uma guilda desta turma.']);
        }

        $team->update([
            'name' => $data['name'],
            'color' => $data['color'],
            'emblem' => $data['emblem'],
        ]);

        $team->members()->sync($memberIds);

        return back()
            ->withInput(['tab' => 'guildas'])
            ->with('success', 'Guilda atualizada.');
    }

    public function destroy(SchoolClass $schoolClass, Team $team): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($team->class_id === $schoolClass->id, 404);
        $team->delete();

        return back()
            ->withInput(['tab' => 'guildas'])
            ->with('success', 'Guilda removida.');
    }

    /**
     * @param  list<int|string>  $memberIds
     * @return list<int>
     */
    private function allowedMemberIds(SchoolClass $schoolClass, array $memberIds): array
    {
        $allowed = $schoolClass->students()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_intersect(array_map('intval', $memberIds), $allowed));
    }

    /**
     * @param  list<int>  $memberIds
     * @return list<int>
     */
    private function takenMemberIds(SchoolClass $schoolClass, array $memberIds, ?int $exceptTeamId = null): array
    {
        if ($memberIds === []) {
            return [];
        }

        $query = DB::table('team_members')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->where('teams.class_id', $schoolClass->id)
            ->whereIn('team_members.student_id', $memberIds);

        if ($exceptTeamId) {
            $query->where('teams.id', '!=', $exceptTeamId);
        }

        return $query->pluck('team_members.student_id')->map(fn ($id) => (int) $id)->all();
    }
}
