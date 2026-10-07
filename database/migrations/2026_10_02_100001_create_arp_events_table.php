<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cria a tabela central da ARP (Armazenamento de Registro de Ponto),
     * atuando como o ledger fiscal único e inalterável do REP-P (Portaria 671/2021 MTP).
     */
    public function up(): void
    {
        Schema::create('arp_events', function (Blueprint $table) {
            $table->string('id', 26)->primary(); // ULID
            $table->foreignId('establishment_id')->constrained('establishments')->restrictOnDelete();
            $table->unsignedBigInteger('nsr');
            $table->string('event_type', 40); // punch, employer_establishment_mutation, worker_mutation, time_sync, rep_sensitive_event
            $table->dateTime('occurred_at_utc');
            $table->dateTime('occurred_at_local');
            $table->string('timezone', 64)->default('America/Maceio');
            $table->string('utc_offset', 6)->default('-03:00');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('fiscal_hash', 64);
            $table->string('audit_chain_hash', 64);
            $table->string('previous_audit_hash', 64)->nullable();
            $table->json('payload')->nullable();
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 36)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['establishment_id', 'nsr'], 'uq_arp_establishment_nsr');
            $table->index(['establishment_id', 'occurred_at_utc']);
            $table->index(['event_type', 'occurred_at_utc']);
            $table->index('fiscal_hash');
            $table->index('audit_chain_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arp_events');
    }
};
