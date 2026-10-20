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
        Schema::table('treatment_events', function (Blueprint $table) {
            $table->unsignedBigInteger('reference_time_entry_id')->nullable()->after('reference_punch_id')->index();
            $table->string('reference_source', 20)->default('punch_event')->after('reference_time_entry_id')->index();

            $table->foreign('reference_time_entry_id')
                ->references('id')
                ->on('time_entries')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatment_events', function (Blueprint $table) {
            $table->dropForeign(['reference_time_entry_id']);
            $table->dropColumn(['reference_time_entry_id', 'reference_source']);
        });
    }
};
