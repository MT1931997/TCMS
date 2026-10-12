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
        Schema::create('notice_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenant_info')->cascadeOnDelete();
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason'); // سبب / تفاصيل الطلب المقدم من المحصل
            $table->string('status')->default('pending_issuance')->index(); // pending_issuance, issued, pending_approval, approved, rejected
            $table->text('notice_content')->nullable(); // نص الإشعار الصادر من المشرف
            $table->string('notice_file_path')->nullable(); // ملف الإشعار الصادر من المشرف
            $table->timestamp('issued_at')->nullable();
            $table->string('delivery_proof_path')->nullable(); // صورة إثبات التسليم المرفوعة من المحصل
            $table->date('delivery_date')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notice_requests');
    }
};
