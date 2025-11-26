<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchase Invoices table
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name');
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->string('invoice_image_path')->nullable();
            $table->timestamps();
        });

        // Purchase Details table
        Schema::create('purchase_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('purchase_invoices')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('quantity', 10, 2);
            $table->decimal('purchase_price', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_details');
        Schema::dropIfExists('purchase_invoices');
    }
};
