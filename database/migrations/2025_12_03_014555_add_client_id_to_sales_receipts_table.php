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
        Schema::table('sales_receipts', function (Blueprint $table) {
            // Add client_id column and remove customer_name
            $table->foreignId('client_id')->nullable()->after('distributor_id')->constrained('clients')->onDelete('set null');
            $table->dropColumn('customer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
            $table->string('customer_name')->nullable()->after('distributor_id');
        });
    }
};
