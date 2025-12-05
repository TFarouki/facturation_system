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
            // Drop old foreign key constraint
            $table->dropForeign(['distributor_id']);
            
            // Update distributor_id to reference distributors table instead of users
            // Note: This assumes data migration has been handled separately if needed
            $table->foreignId('distributor_id')->change();
            $table->foreign('distributor_id')->references('id')->on('distributors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['distributor_id']);
            
            // Revert to users table (if needed)
            $table->foreignId('distributor_id')->change();
            $table->foreign('distributor_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
