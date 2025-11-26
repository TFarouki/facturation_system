<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sales Receipts table
        Schema::create('sales_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('distributor_cycles')->onDelete('cascade');
            $table->foreignId('distributor_id')->constrained('users')->onDelete('cascade');
            $table->string('customer_name')->nullable();
            $table->date('receipt_date');
            $table->string('receipt_image_path')->nullable();
            $table->timestamps();
        });

        // Sales Details table
        Schema::create('sales_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('sales_receipts')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('quantity', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->enum('price_type_used', ['wholesale', 'semi_wholesale', 'installment']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_details');
        Schema::dropIfExists('sales_receipts');
    }
};
