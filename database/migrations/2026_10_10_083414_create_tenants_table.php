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
        Schema::create('tenant_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('tenant_number')->unique()->index();
            $table->string('property_unit')->index();
            $table->date('due_date')->index();
            $table->decimal('monthly_target', 12, 2)->default(0);
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('national_id')->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('regular')->index(); // regular (منتظم), late (متأخر), paid (مسدد)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_info');
    }
};
