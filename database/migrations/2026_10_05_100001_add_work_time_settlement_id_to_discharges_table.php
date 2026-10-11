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
        Schema::table('work_time_settlement_discharges', function (Blueprint $table) {
            $table->ulid('work_time_settlement_id')->nullable()->unique()->after('closed_period_id')->comment('Referência única ao settlement original para evitar dupla execução');
            $table->foreign('work_time_settlement_id')->references('id')->on('work_time_settlements')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_time_settlement_discharges', function (Blueprint $table) {
            $table->dropForeign(['work_time_settlement_id']);
            $table->dropColumn('work_time_settlement_id');
        });
    }
};
