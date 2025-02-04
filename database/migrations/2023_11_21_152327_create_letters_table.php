<?php

use App\Models\Letter;
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
        Schema::create('letters', function (Blueprint $table) {
          $table->id();
          $table->foreignId('user_id')->constrained('users');
          $table->string('subject')->index();
          if (DB::getDriverName() !== 'sqlite') {
            $table->longText('text')->nullable()->fulltext();
          } else {
            $table->longText('text')->nullable();
          }
          $table->string('status')->index();
          $table->string('description')->nullable();
          $table->json('meta')->nullable();
          $table->string('priority')->default(Letter::PRIORITY_NORMAL)->index();
          $table->timestamp('submitted_at')->nullable()->index();
          $table->date('due_date')->nullable();
          $table->string('category')->default(Letter::CATEGORY_NORMAL)->index();
          $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
