<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('company_logo')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();
            $table->decimal('semi_wholesale_percentage', 5, 2)->default(0);
            $table->decimal('retail_percentage', 5, 2)->default(0);
            $table->timestamps();
        });

        // Insert default settings row
        DB::table('settings')->insert([
            'company_name' => 'My Company',
            'semi_wholesale_percentage' => 10.00,
            'retail_percentage' => 20.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
