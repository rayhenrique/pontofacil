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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('job_title')->nullable()->default('Servidor Público')->after('phone');
            $table->string('contract_type')->nullable()->default('Efetivo')->after('job_title');
            $table->string('workload')->nullable()->default('40h')->after('contract_type');
            $table->string('zone')->nullable()->default('Urbana')->after('workload');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'contract_type', 'workload', 'zone']);
        });
    }
};
