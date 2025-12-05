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
        Schema::create('product_families', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Update products table to use foreign key
        Schema::table('products', function (Blueprint $table) {
            // First, add a new column for the foreign key
            $table->foreignId('product_family_id')->nullable()->after('product_family')->constrained('product_families')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['product_family_id']);
            $table->dropColumn('product_family_id');
        });

        Schema::dropIfExists('product_families');
    }
};
