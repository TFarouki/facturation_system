<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Distributor Cycles table
        Schema::create('distributor_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained('users')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
        });

        // Cycle Movements table
        Schema::create('cycle_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('distributor_cycles')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('quantity', 10, 2);
            $table->enum('movement_type', ['load', 'reload', 'return']);
            $table->timestamp('movement_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_movements');
        Schema::dropIfExists('distributor_cycles');
    }
};
