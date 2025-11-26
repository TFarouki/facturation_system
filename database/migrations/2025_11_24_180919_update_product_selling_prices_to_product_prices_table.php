<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename table
        Schema::rename('product_selling_prices', 'product_prices');
        
        // Modify the table structure
        Schema::table('product_prices', function (Blueprint $table) {
            // Rename installment_price to retail_price
            $table->renameColumn('installment_price', 'retail_price');
            
            // Drop last_purchase_cost (now tracked via CMUP in products table)
            $table->dropColumn('last_purchase_cost');
            
            // Add new columns
            $table->date('effective_date')->default(now())->after('retail_price');
            $table->boolean('is_current')->default(true)->after('effective_date');
            
            // Add index for faster queries on current prices
            $table->index(['product_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            // Remove new columns
            $table->dropIndex(['product_id', 'is_current']);
            $table->dropColumn(['effective_date', 'is_current']);
            
            // Add back last_purchase_cost
            $table->decimal('last_purchase_cost', 10, 2)->default(0)->after('retail_price');
            
            // Rename retail_price back to installment_price
            $table->renameColumn('retail_price', 'installment_price');
        });
        
        // Rename table back
        Schema::rename('product_prices', 'product_selling_prices');
    }
};
