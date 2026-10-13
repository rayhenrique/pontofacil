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
        Schema::table('monthly_attendance_reviews', function (Blueprint $table) {
            // Hash determinístico dos insumos da apuração analisados na conferência.
            // Nulo em conferências legadas (tratadas com validação conservadora).
            $table->string('fingerprint', 64)->nullable()->after('reservation_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_attendance_reviews', function (Blueprint $table) {
            $table->dropColumn('fingerprint');
        });
    }
};
