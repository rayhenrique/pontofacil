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
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->string('recognized_turn', 32)
                ->nullable()
                ->after('crosses_midnight')
                ->comment('Turno reconhecido: day, night, pending_classification, undefined');

            $table->index(['employee_id', 'recognized_turn']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'recognized_turn']);
            $table->dropColumn('recognized_turn');
        });
    }
};
