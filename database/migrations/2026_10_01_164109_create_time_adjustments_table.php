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
        Schema::create('time_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_entry_id')->constrained('time_entries')->cascadeOnDelete();
            $table->foreignId('adjusted_by')->constrained('users');
            $table->dateTime('old_timestamp')->nullable();
            $table->dateTime('new_timestamp');
            $table->string('justification', 255);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_adjustments');
    }
};
