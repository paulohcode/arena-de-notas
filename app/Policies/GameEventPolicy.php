<?php

namespace App\Policies;

use App\Models\GameEvent;
use App\Models\User;

class GameEventPolicy
{
    public function view(User $user, GameEvent $gameEvent): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher()) {
            return $this->manage($user, $gameEvent);
        }

        if (! $user->isStudent()) {
            return false;
        }

        if ($gameEvent->isRealmEvent()) {
            return $user->enrollments()
                ->whereHas('schoolClass', fn ($q) => $q->where('area_id', $gameEvent->area_id))
                ->exists();
        }

        return $user->enrollments()->where('class_id', $gameEvent->class_id)->exists();
    }

    public function manage(User $user, GameEvent $gameEvent): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isTeacher()) {
            return false;
        }

        if ($gameEvent->isRealmEvent()) {
            return $user->belongsToArea((int) $gameEvent->area_id);
        }

        return $gameEvent->schoolClass
            && $gameEvent->schoolClass->teacher_id === $user->id
            && $user->belongsToArea((int) $gameEvent->schoolClass->area_id);
    }

    public function play(User $user, GameEvent $gameEvent): bool
    {
        return $user->isStudent() && $this->view($user, $gameEvent);
    }
}
