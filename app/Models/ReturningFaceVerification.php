<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturningFaceVerification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'match_score' => 'decimal:2',
        'detection_confidence' => 'decimal:2',
        'checked_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(VerifiedVisitor::class, 'visitor_id');
    }
}
