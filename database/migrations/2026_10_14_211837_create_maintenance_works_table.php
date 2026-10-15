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
        Schema::create('maintenance_works', function (Blueprint $table) {
            $table->id();
            $table->string('work_order_number')->unique()->index();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('maintenance_request_id')->nullable()->constrained('maintenance_requests')->nullOnDelete();
            $table->string('property_unit');
            $table->string('title');
            $table->text('description');
            $table->json('attachments')->nullable();
            $table->string('status')->default('pending')->index(); // pending, approved, rejected
            $table->timestamp('owner_decision_at')->nullable();
            $table->text('owner_notes')->nullable();
            $table->text('owner_rejection_reason')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1 to 5
            $table->text('rating_comment')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_works');
    }
};
