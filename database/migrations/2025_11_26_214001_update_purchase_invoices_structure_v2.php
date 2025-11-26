<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            // Add new columns
            $table->decimal('total_in_invoice', 10, 2)->nullable()->after('total_amount');
            $table->boolean('it_has_def')->default(false)->after('total_in_invoice');
            
            // Drop the old column if it exists
            if (Schema::hasColumn('purchase_invoices', 'is_total_mismatch')) {
                $table->dropColumn('is_total_mismatch');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn(['total_in_invoice', 'it_has_def']);
            $table->boolean('is_total_mismatch')->default(false);
        });
    }
};
