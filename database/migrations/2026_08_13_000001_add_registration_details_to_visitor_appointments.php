<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_appointments', function (Blueprint $table) {
            $table->string('registration_token', 64)->nullable()->unique()->after('status');
            $table->timestamp('registration_completed_at')->nullable()->after('registration_token');
        });

        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->foreignId('visitor_appointment_id')->nullable()->unique()->after('id')
                ->constrained('visitor_appointments')->nullOnDelete();
            $table->text('purpose')->nullable()->after('company');
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visitor_appointment_id');
            $table->dropColumn('purpose');
        });

        Schema::table('visitor_appointments', function (Blueprint $table) {
            $table->dropUnique(['registration_token']);
            $table->dropColumn(['registration_token', 'registration_completed_at']);
        });
    }
};
