<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VerifiedVisitor extends Model
{
    protected $guarded = [];

    protected $casts = [
        'entrance_fee' => 'decimal:2',
        'checkin_status' => 'boolean',
        'verified_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'face_match_score' => 'decimal:2',
        'face_detection_confidence' => 'decimal:2',
        'face_verified_at' => 'datetime',
        'identity_reviewed_at' => 'datetime',
        'is_blocked' => 'boolean',
        'visitor_count' => 'integer',
        'approval_requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'visitor_pass_issued_at' => 'datetime',
        'visitor_pass_returned_at' => 'datetime',
    ];

    /**
     * Determine whether this NIC already belongs to a submitted registration.
     *
     * Identity-only and face-only records are temporary verification attempts,
     * so they do not prevent the visitor from retrying an incomplete attempt.
     */
    public static function hasSubmittedNicRegistration(string $nicNumber, ?string $exceptVerificationId = null): bool
    {
        $nicNumber = strtoupper((string) preg_replace('/\s+/', '', $nicNumber));

        if ($nicNumber === '') {
            return false;
        }

        $query = static::query()
            ->where('document_type', 'nic')
            ->whereNotIn('registration_status', ['identity_verified', 'face_verified'])
            ->whereRaw('UPPER(REPLACE(document_number, ?, ?)) = ?', [' ', '', $nicNumber]);

        if ($exceptVerificationId !== null && $exceptVerificationId !== '') {
            $query->where('verification_id', '!=', $exceptVerificationId);
        }

        return $query->exists();
    }

    public function gateLogs(): HasMany
    {
        return $this->hasMany(GateLog::class, 'visitor_id');
    }

    public function returningFaceVerifications(): HasMany
    {
        return $this->hasMany(ReturningFaceVerification::class, 'visitor_id');
    }

    public function latestReturningFaceVerification(): HasOne
    {
        return $this->hasOne(ReturningFaceVerification::class, 'visitor_id')->latestOfMany('checked_at');
    }
}
