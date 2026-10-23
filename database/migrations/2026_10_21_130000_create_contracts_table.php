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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number')->nullable()->index();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenant_info')->cascadeOnDelete();
            $table->string('unit_number')->index();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('payment_cycle')->default('monthly'); // monthly, quarterly, semi_annual, annual
            $table->decimal('installment_amount', 12, 2)->default(0);
            $table->string('claim_generation_mode')->default('current_month_only'); // current_month_only, from_start_date, none
            $table->string('status')->default('active')->index(); // active, renewed, closed
            $table->foreignId('previous_contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
