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
            // Drop foreign key constraint first
            $table->dropForeign(['cycle_id']);
            // Then drop the column
            $table->dropColumn('cycle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_receipts', function (Blueprint $table) {
            // Add back cycle_id column
            $table->foreignId('cycle_id')->nullable()->constrained('distributor_cycles')->onDelete('cascade');
        });
    }
};
