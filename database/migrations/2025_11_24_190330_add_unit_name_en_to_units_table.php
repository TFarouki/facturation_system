<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('unit_name_en')->nullable()->after('unit_name_ar');
        });

        // Update existing units with English translations
        DB::table('units')->where('unit_name_ar', 'قطعة')->update(['unit_name_en' => 'Piece']);
        DB::table('units')->where('unit_name_ar', 'كيلوغرام')->update(['unit_name_en' => 'Kilogram']);
        DB::table('units')->where('unit_name_ar', 'لتر')->update(['unit_name_en' => 'Liter']);
        DB::table('units')->where('unit_name_ar', 'متر')->update(['unit_name_en' => 'Meter']);
        DB::table('units')->where('unit_name_ar', 'علبة')->update(['unit_name_en' => 'Box']);
        DB::table('units')->where('unit_name_ar', 'كرتون')->update(['unit_name_en' => 'Carton']);
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('unit_name_en');
        });
    }
};
