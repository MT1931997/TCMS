<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropIndex(['contract_number']);
                $table->string('contract_number')->nullable()->unique()->change();
            });

            Schema::table('tenant_info', function (Blueprint $table) {
                $table->string('tenant_number')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropUnique(['contract_number']);
            $table->string('contract_number')->nullable()->index()->change();
        });

        Schema::table('tenant_info', function (Blueprint $table) {
            $table->string('tenant_number')->change();
        });
    }
};
