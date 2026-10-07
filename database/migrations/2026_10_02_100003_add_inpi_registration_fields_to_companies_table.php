<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona estrutura regulatória para futuro armazenamento do registro oficial no INPI (Portaria 671/2021 MTP).
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('inpi_registration_number', 50)->nullable()->after('rep_p_software_version');
            $table->date('inpi_registration_date')->nullable()->after('inpi_registration_number');
            $table->string('inpi_registration_status', 30)->default('pending_registration')->after('inpi_registration_date');
            $table->string('inpi_certificate_path')->nullable()->after('inpi_registration_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'inpi_registration_number',
                'inpi_registration_date',
                'inpi_registration_status',
                'inpi_certificate_path',
            ]);
        });
    }
};
