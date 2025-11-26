<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('id')->constrained('suppliers')->onDelete('set null');
            $table->decimal('total_amount', 10, 2)->default(0)->after('invoice_image_path');
            $table->text('notes')->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'total_amount', 'notes']);
        });
    }
};
