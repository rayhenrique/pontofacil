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
        Schema::table('closed_periods', function (Blueprint $table) {
            $table->unsignedInteger('snapshot_version')->default(1)->after('policy_snapshot');
            $table->string('snapshot_hash', 64)->nullable()->after('snapshot_version');
            $table->unsignedInteger('employees_count')->default(0)->after('snapshot_hash');
            $table->unsignedInteger('punches_count')->default(0)->after('employees_count');
            $table->unsignedInteger('treatments_count')->default(0)->after('punches_count');
            $table->dateTime('generated_at')->nullable()->after('treatments_count');

            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete()->after('closed_at');
            $table->dateTime('reopened_at')->nullable()->after('reopened_by');
            $table->text('reopen_reason')->nullable()->after('reopened_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('closed_periods', function (Blueprint $table) {
            $table->dropForeign(['reopened_by']);
            $table->dropColumn([
                'snapshot_version',
                'snapshot_hash',
                'employees_count',
                'punches_count',
                'treatments_count',
                'generated_at',
                'reopened_by',
                'reopened_at',
                'reopen_reason',
            ]);
        });
    }
};
