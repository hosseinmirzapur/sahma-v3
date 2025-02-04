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
        Schema::create('letter_inboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->nullable()->constrained('letters');
            $table->foreignId('user_id')->constrained('users');
            $table->string('read_status')->default(0)->index();
            $table->string('is_refer')->default(0)->index();
            $table->foreignId('referred_by')->nullable()->constrained('users');
            $table->string('refer_description')->nullable();
            $table->date('due_date')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_inboxes');
    }
};
