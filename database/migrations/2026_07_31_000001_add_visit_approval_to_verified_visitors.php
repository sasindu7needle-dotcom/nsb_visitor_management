<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('department')->nullable()->after('company');
            $table->string('person_to_meet')->nullable()->after('department');
            $table->unsignedSmallInteger('visitor_count')->default(1)->after('person_to_meet');
            $table->string('expected_gate', 80)->default('Main Gate')->after('visitor_count');
            $table->string('approval_status', 30)->default('approved')->index()->after('expected_gate');
            $table->timestamp('approval_requested_at')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approval_requested_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'department',
                'person_to_meet',
                'visitor_count',
                'expected_gate',
                'approval_status',
                'approval_requested_at',
                'approved_at',
            ]);
        });
    }
};
