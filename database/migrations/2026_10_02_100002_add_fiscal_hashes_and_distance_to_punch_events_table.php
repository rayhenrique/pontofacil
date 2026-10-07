<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona campos de hash fiscal MTE, encadeamento de auditoria interna e distância calculada à punch_events.
     */
    public function up(): void
    {
        Schema::table('punch_events', function (Blueprint $table) {
            $table->string('fiscal_hash', 64)->nullable()->after('location_valid');
            $table->string('audit_chain_hash', 64)->nullable()->after('fiscal_hash');
            $table->string('previous_audit_hash', 64)->nullable()->after('audit_chain_hash');
            $table->decimal('location_distance_meters', 10, 2)->nullable()->after('location_accuracy');

            $table->index('fiscal_hash');
            $table->index('audit_chain_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('punch_events', function (Blueprint $table) {
            $table->dropIndex(['fiscal_hash']);
            $table->dropIndex(['audit_chain_hash']);
            $table->dropColumn([
                'fiscal_hash',
                'audit_chain_hash',
                'previous_audit_hash',
                'location_distance_meters',
            ]);
        });
    }
};
