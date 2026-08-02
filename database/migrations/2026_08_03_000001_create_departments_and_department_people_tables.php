<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('department_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['department_id', 'name']);
        });

        $now = now();
        $departments = [
            'Finance Department',
            'Human Resources',
            'Information Technology',
            'Operations Department',
            'Administration',
        ];

        foreach ($departments as $name) {
            DB::table('departments')->insert([
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $departmentIds = DB::table('departments')->pluck('id', 'name');
        $people = [
            ['Finance Department', 'Ms. Nirosha Fernando'],
            ['Human Resources', 'Mr. Kasun Perera'],
            ['Information Technology', 'Ms. Amaya Silva'],
            ['Operations Department', 'Mr. Dinesh Jayawardena'],
        ];

        foreach ($people as [$department, $name]) {
            DB::table('department_people')->insert([
                'department_id' => $departmentIds[$department],
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_people');
        Schema::dropIfExists('departments');
    }
};
