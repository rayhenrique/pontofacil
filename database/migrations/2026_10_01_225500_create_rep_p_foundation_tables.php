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
        // 1. Empresa Única da Instalação (Single-Tenant)
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name', 150);
            $table->string('trade_name', 100);
            $table->string('cnpj', 14)->unique();
            $table->string('rep_p_software_name', 50)->default('PontoFácil');
            $table->string('rep_p_software_version', 20)->default('2.0.0');
            $table->timestamps();
        });

        // 2. Estabelecimentos (Matriz / Filiais com NSR Monotônico Independente)
        Schema::create('establishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('code', 20)->default('MATRIZ');
            $table->string('name', 100);
            $table->string('identifier_type', 10)->default('cnpj'); // cnpj, cpf, cno, caepf
            $table->string('identifier_number', 14);
            $table->string('address')->nullable();
            $table->string('city', 100)->default('Maceió');
            $table->string('state', 2)->default('AL');
            $table->string('postal_code', 10)->nullable();
            $table->string('timezone', 64)->default('America/Maceio');
            $table->unsignedBigInteger('nsr_next')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        // 3. Vínculo opcional de Setor ao Estabelecimento
        Schema::table('sectors', function (Blueprint $table) {
            $table->foreignId('establishment_id')->nullable()->after('id')->constrained('establishments')->nullOnDelete();
        });

        // 4. Ledger Imutável de Marcações (REP-P / Portaria 671 MTP)
        Schema::create('punch_events', function (Blueprint $table) {
            $table->string('id', 26)->primary(); // ULID
            $table->foreignId('establishment_id')->constrained('establishments')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('nsr');
            $table->dateTime('occurred_at_utc');
            $table->dateTime('occurred_at_local');
            $table->string('timezone', 64)->default('America/Maceio');
            $table->string('utc_offset', 6)->default('-03:00');
            $table->string('direction', 10); // in, out
            $table->string('source', 32)->default('web_pwa'); // web_pwa, mobile_app, kiosk
            $table->string('collector_type', 32)->default('browser');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('location_accuracy', 8, 2)->nullable();
            $table->boolean('qr_location_valid')->nullable();
            $table->boolean('location_valid')->nullable();
            $table->string('payload_hash', 64);
            $table->string('previous_event_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['establishment_id', 'nsr']);
            $table->index(['employee_id', 'occurred_at_utc']);
            $table->index(['establishment_id', 'occurred_at_utc']);
            $table->index('payload_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('punch_events');

        Schema::table('sectors', function (Blueprint $table) {
            $table->dropForeign(['establishment_id']);
            $table->dropColumn('establishment_id');
        });

        Schema::dropIfExists('establishments');
        Schema::dropIfExists('companies');
    }
};
