<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->string('visitor_name', 180);
            $table->string('email')->nullable();
            $table->string('phone', 20);
            $table->string('company', 150)->nullable();
            $table->unsignedTinyInteger('visitor_count')->default(1);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_person_id')->nullable()->constrained('department_people')->nullOnDelete();
            $table->timestamp('scheduled_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->text('purpose');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('scheduled')->index();
            $table->string('created_by', 180)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_appointments');
    }
};
