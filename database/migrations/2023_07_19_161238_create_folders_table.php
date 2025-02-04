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
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('parent_folder_id')->nullable()->constrained('folders');
            $table->timestamp('deleted_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->string('slug')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
