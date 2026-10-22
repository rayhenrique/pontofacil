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
        Schema::table('labor_rule_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('labor_rule_profiles', 'entity_name')) {
                $table->string('entity_name', 150)->nullable()->after('jurisdiction');
            }
            if (! Schema::hasColumn('labor_rule_profiles', 'revoked_by')) {
                $table->foreignId('revoked_by')->nullable()->after('approved_at')->constrained('users', 'id', 'fk_lrp_revoked_by')->nullOnDelete();
            }
            if (! Schema::hasColumn('labor_rule_profiles', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('revoked_by');
            }
            if (! Schema::hasColumn('labor_rule_profiles', 'revocation_reason')) {
                $table->text('revocation_reason')->nullable()->after('revoked_at');
            }
            if (! Schema::hasColumn('labor_rule_profiles', 'change_reason')) {
                $table->text('change_reason')->nullable()->after('revocation_reason');
            }
            if (! Schema::hasColumn('labor_rule_profiles', 'parent_profile_id')) {
                $table->foreignId('parent_profile_id')->nullable()->after('version')->constrained('labor_rule_profiles', 'id', 'fk_lrp_parent')->nullOnDelete();
            }
        });

        Schema::dropIfExists('employee_labor_rule_profile_assignments');

        Schema::create('employee_labor_rule_profile_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees', 'id', 'fk_erpa_emp')->restrictOnDelete();
            $table->foreignId('labor_rule_profile_id')->constrained('labor_rule_profiles', 'id', 'fk_erpa_profile')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users', 'id', 'fk_erpa_user')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from'], 'idx_emp_rule_effective');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_labor_rule_profile_assignments');

        Schema::table('labor_rule_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('labor_rule_profiles', 'parent_profile_id')) {
                $table->dropForeign('fk_lrp_parent');
            }
            if (Schema::hasColumn('labor_rule_profiles', 'revoked_by')) {
                $table->dropForeign('fk_lrp_revoked_by');
            }
            $table->dropColumn(array_filter([
                Schema::hasColumn('labor_rule_profiles', 'entity_name') ? 'entity_name' : null,
                Schema::hasColumn('labor_rule_profiles', 'revoked_by') ? 'revoked_by' : null,
                Schema::hasColumn('labor_rule_profiles', 'revoked_at') ? 'revoked_at' : null,
                Schema::hasColumn('labor_rule_profiles', 'revocation_reason') ? 'revocation_reason' : null,
                Schema::hasColumn('labor_rule_profiles', 'change_reason') ? 'change_reason' : null,
                Schema::hasColumn('labor_rule_profiles', 'parent_profile_id') ? 'parent_profile_id' : null,
            ]));
        });
    }
};
