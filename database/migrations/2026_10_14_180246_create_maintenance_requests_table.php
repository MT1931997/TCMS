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
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique()->index();
            $table->string('tenant_name');
            $table->string('national_id')->index();
            $table->date('birth_date');
            $table->string('phone')->index();
            $table->string('property_unit');
            $table->string('title');
            $table->text('description');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address')->nullable();
            $table->json('attachments')->nullable();
            $table->string('supervisor_status')->default('pending_review')->index(); // pending_review, approved, rejected
            $table->string('owner_status')->nullable()->index(); // pending, approved, rejected
            $table->foreignId('tenant_id')->nullable()->constrained('tenant_info')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('owner_decision_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('owner_notes')->nullable();
            $table->text('owner_rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
