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
        Schema::create('punch_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('punch_event_id', 26)->unique();
            $table->foreign('punch_event_id')->references('id')->on('punch_events')->restrictOnDelete();
            $table->string('verification_code', 32)->unique();
            $table->string('receipt_hash', 64);
            $table->string('signature_status', 20)->default('unsigned'); // 'unsigned', 'signed', 'signed_dev'
            $table->dateTime('signed_at')->nullable();
            $table->json('signature_metadata')->nullable();
            $table->timestamps();

            $table->index('verification_code');
            $table->index('receipt_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('punch_receipts');
    }
};
