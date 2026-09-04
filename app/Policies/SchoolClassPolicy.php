<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function manage(User $user, SchoolClass $schoolClass): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher()
            && $schoolClass->teacher_id === $user->id
            && $user->belongsToArea((int) $schoolClass->area_id);
    }

    /**
     * Professor do mesmo reino (ou admin) vê nomes e notas ocultas no ranking.
     */
    public function viewStaffDetails(User $user, SchoolClass $schoolClass): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher() && $user->belongsToArea((int) $schoolClass->area_id);
    }

    public function viewAsStudent(User $user, SchoolClass $schoolClass): bool
    {
        return $user->isStudent() && $user->enrollments()->where('class_id', $schoolClass->id)->exists();
    }
}
