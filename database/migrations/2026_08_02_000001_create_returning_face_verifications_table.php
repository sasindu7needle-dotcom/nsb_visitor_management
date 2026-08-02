<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('returning_face_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('verified_visitors')->cascadeOnDelete();
            $table->string('nic_number')->index();
            $table->string('photo_path');
            $table->string('photo_mime', 100)->nullable();
            $table->string('status', 30)->index(); // same, different, or review_required
            $table->decimal('match_score', 5, 2)->nullable();
            $table->decimal('detection_confidence', 5, 2)->nullable();
            $table->string('provider', 60)->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();

            $table->index(['visitor_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returning_face_verifications');
    }
};
