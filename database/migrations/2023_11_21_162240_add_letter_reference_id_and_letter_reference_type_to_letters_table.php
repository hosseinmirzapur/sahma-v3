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
        Schema::table('letters', function (Blueprint $table) {
          $table->string('letter_reference_type')->nullable();
          $table->foreignId('letter_reference_id')->nullable()->constrained('letters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table) {
          $table->dropColumn('letter_reference_type');
          $table->dropConstrainedForeignId('letter_reference_id');
        });
    }
};
