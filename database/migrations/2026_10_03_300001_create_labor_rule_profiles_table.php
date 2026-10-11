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
        Schema::dropIfExists('labor_rule_profiles');

        Schema::create('labor_rule_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 60)->unique();
            $table->string('legal_regime', 40)->index();
            $table->string('jurisdiction', 30)->default('federal');
            $table->string('legal_reference', 255);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('approval_status', 30)->default('approved');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->text('description')->nullable();
            $table->json('configuration');
            $table->timestamps();

            $table->index(['legal_regime', 'approval_status', 'effective_from'], 'idx_labor_rule_regime_status_effective');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labor_rule_profiles');
    }
};
