<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona chave de idempotência com restrição de unicidade à tabela punch_events.
     */
    public function up(): void
    {
        Schema::table('punch_events', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('payload_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('punch_events', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
