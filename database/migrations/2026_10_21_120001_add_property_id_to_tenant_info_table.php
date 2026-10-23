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
        Schema::table('tenant_info', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('owner_id')->constrained('properties')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_info', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
        });
    }
};
