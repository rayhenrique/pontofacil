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
        Schema::table('sectors', function (Blueprint $table) {
            $table->string('qr_code_hash')->nullable()->after('manager_id');
            $table->decimal('latitude', 10, 8)->nullable()->after('qr_code_hash');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->integer('allowed_radius_meters')->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn(['qr_code_hash', 'latitude', 'longitude', 'allowed_radius_meters']);
        });
    }
};
