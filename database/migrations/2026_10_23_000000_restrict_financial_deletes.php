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
            $table->dropForeign(['collector_id']);
            $table->foreign('collector_id')->references('id')->on('users')->restrictOnDelete();
            $table->softDeletes();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenant_info')->restrictOnDelete();
            $table->softDeletes();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->dropForeign(['tenant_id']);
            $table->foreign('property_id')->references('id')->on('properties')->restrictOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenant_info')->restrictOnDelete();
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('maintenance_works', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_works', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->dropForeign(['tenant_id']);
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenant_info')->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenant_info')->cascadeOnDelete();
            $table->dropSoftDeletes();
        });

        Schema::table('tenant_info', function (Blueprint $table) {
            $table->dropForeign(['collector_id']);
            $table->foreign('collector_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dropSoftDeletes();
        });
    }
};
