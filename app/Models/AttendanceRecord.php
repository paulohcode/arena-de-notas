<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_JUSTIFIED = 'justified';

    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_JUSTIFIED,
    ];

    protected $fillable = [
        'attendance_session_id',
        'student_id',
        'status',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function countsTowardAttendance(): bool
    {
        return in_array($this->status, [self::STATUS_PRESENT, self::STATUS_JUSTIFIED], true);
    }

    public function awardsSeal(): bool
    {
        return $this->status === self::STATUS_PRESENT;
    }
}
