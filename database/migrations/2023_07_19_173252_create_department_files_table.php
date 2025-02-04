<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('department_files', function (Blueprint $table) {
          $table->id();
          $table->foreignId('entity_group_id')->constrained('entity_groups');
          $table->foreignId('department_id')->constrained('departments');
          $table->unique(['entity_group_id', 'department_id']);
          $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_users');
    }
};
