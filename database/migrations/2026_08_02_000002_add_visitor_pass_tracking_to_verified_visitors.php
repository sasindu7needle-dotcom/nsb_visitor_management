<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('visitor_pass_number', 50)->nullable()->index()->after('expected_gate');
            $table->timestamp('visitor_pass_issued_at')->nullable()->after('visitor_pass_number');
            $table->timestamp('visitor_pass_returned_at')->nullable()->after('visitor_pass_issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropColumn([
                'visitor_pass_number',
                'visitor_pass_issued_at',
                'visitor_pass_returned_at',
            ]);
        });
    }
};
