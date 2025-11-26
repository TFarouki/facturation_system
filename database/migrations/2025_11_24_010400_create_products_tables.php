<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products table
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('product_code')->nullable(); // SKU
            $table->string('barcode')->nullable();
            $table->string('unit_of_measure')->default('unit');
            $table->decimal('current_stock_quantity', 10, 2)->default(0);
            $table->decimal('cmup_cost', 10, 2)->default(0); // Weighted Average Cost
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->timestamps();
        });

        // Product Selling Prices table
        Schema::create('product_selling_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('wholesale_price', 10, 2)->default(0);
            $table->decimal('semi_wholesale_price', 10, 2)->default(0);
            $table->decimal('installment_price', 10, 2)->default(0);
            $table->decimal('last_purchase_cost', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_selling_prices');
        Schema::dropIfExists('products');
    }
};
