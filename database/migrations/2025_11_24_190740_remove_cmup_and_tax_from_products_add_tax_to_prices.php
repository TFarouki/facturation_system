<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove cmup_cost and tax_rate from products table
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['cmup_cost', 'tax_rate']);
        });

        // Add tax_rate to product_prices table
        Schema::table('product_prices', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('retail_price');
        });
    }

    public function down(): void
    {
        // Add back cmup_cost and tax_rate to products
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cmup_cost', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
        });

        // Remove tax_rate from product_prices
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
