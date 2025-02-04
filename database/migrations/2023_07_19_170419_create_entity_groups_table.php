<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entity_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('parent_folder_id')->nullable()->constrained('folders');
            $table->string('name')->index();
            $table->string('type')->index();
          if (DB::getDriverName() !== 'sqlite') {
            $table->longText('transcription_result')->nullable()->fulltext();
          } else {
            $table->longText('transcription_result')->nullable();
          }
            $table->timestamp('transcription_at')->nullable()->index();
            $table->string('status')->index();
            $table->json('meta')->nullable();
            $table->string('file_location');
            $table->string('description')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->json('result_location')->nullable();
            $table->integer('number_of_try')->default(0)->index();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->string('slug')->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_groups');
    }
};
