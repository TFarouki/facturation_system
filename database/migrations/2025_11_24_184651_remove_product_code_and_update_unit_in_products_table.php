<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Remove product_code (SKU)
            $table->dropColumn('product_code');
            
            // Drop old unit_of_measure column
            $table->dropColumn('unit_of_measure');
            
            // Add unit_id as foreign key
            $table->foreignId('unit_id')->nullable()->after('name')->constrained('units')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Remove foreign key and unit_id
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
            
            // Add back unit_of_measure
            $table->string('unit_of_measure')->default('unit');
            
            // Add back product_code
            $table->string('product_code')->nullable();
        });
    }
};
