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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('cnpj');
            $table->string('phone', 30)->nullable()->after('logo_path');
            $table->string('email', 100)->nullable()->after('phone');
            $table->string('address')->nullable()->after('email');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 50)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->string('header_state', 100)->nullable()->after('postal_code');
            $table->string('header_entity', 150)->nullable()->after('header_state');
            $table->string('header_sub_entity', 150)->nullable()->after('header_entity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path',
                'phone',
                'email',
                'address',
                'city',
                'state',
                'postal_code',
                'header_state',
                'header_entity',
                'header_sub_entity',
            ]);
        });
    }
};
