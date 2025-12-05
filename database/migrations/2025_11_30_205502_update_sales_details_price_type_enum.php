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
        // Change enum from 'installment' to 'retail'
        // First, update existing data
        \Illuminate\Support\Facades\DB::statement("
            UPDATE sales_details 
            SET price_type_used = 'retail' 
            WHERE price_type_used = 'installment'
        ");
        
        // Then modify the enum column
        \Illuminate\Support\Facades\DB::statement("
            ALTER TABLE sales_details 
            MODIFY COLUMN price_type_used ENUM('wholesale', 'semi_wholesale', 'retail') 
            NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change enum from 'retail' back to 'installment'
        // First, update existing data
        \Illuminate\Support\Facades\DB::statement("
            UPDATE sales_details 
            SET price_type_used = 'installment' 
            WHERE price_type_used = 'retail'
        ");
        
        // Then modify the enum column back
        \Illuminate\Support\Facades\DB::statement("
            ALTER TABLE sales_details 
            MODIFY COLUMN price_type_used ENUM('wholesale', 'semi_wholesale', 'installment') 
            NOT NULL
        ");
    }
};
