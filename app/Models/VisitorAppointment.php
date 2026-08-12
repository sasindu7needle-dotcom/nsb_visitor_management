<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorAppointment extends Model
{
    protected $fillable = [
        'reference',
        'visitor_name',
        'email',
        'phone',
        'company',
        'visitor_count',
        'department_id',
        'department_person_id',
        'scheduled_at',
        'duration_minutes',
        'purpose',
        'notes',
        'status',
        'registration_token',
        'registration_completed_at',
        'created_by',
    ];

    protected $hidden = [
        'registration_token',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'registration_completed_at' => 'datetime',
        'visitor_count' => 'integer',
        'duration_minutes' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function personToMeet(): BelongsTo
    {
        return $this->belongsTo(DepartmentPerson::class, 'department_person_id');
    }

    /**
     * Check whether the URL token grants access to this appointment's
     * visitor-registration journey.
     */
    public function hasValidRegistrationToken(string $token): bool
    {
        return filled($this->registration_token)
            && hash_equals($this->registration_token, hash('sha256', $token));
    }
}
