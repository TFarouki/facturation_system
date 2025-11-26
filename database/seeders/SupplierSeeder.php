<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Global Tech Supplies', 'contact_person' => 'Ahmed Hassan', 'phone' => '0612345678', 'email' => 'contact@globaltech.ma'],
            ['name' => 'Premium Foods Co.', 'contact_person' => 'Fatima Zahra', 'phone' => '0623456789', 'email' => 'info@premiumfoods.ma'],
            ['name' => 'Fashion House Morocco', 'contact_person' => 'Youssef Alami', 'phone' => '0634567890', 'email' => 'sales@fashionhouse.ma'],
            ['name' => 'Office Pro Maroc', 'contact_person' => 'Samira Bennani', 'phone' => '0645678901', 'email' => 'orders@officepro.ma'],
            ['name' => 'Electronics Plus', 'contact_person' => 'Karim Idrissi', 'phone' => '0656789012', 'email' => 'support@electronicsplus.ma'],
            ['name' => 'Home & Garden Center', 'contact_person' => 'Nadia Tazi', 'phone' => '0667890123', 'email' => 'info@homeandgarden.ma'],
            ['name' => 'Sports World', 'contact_person' => 'Omar Benjelloun', 'phone' => '0678901234', 'email' => 'contact@sportsworld.ma'],
            ['name' => 'Book Haven', 'contact_person' => 'Laila Amrani', 'phone' => '0689012345', 'email' => 'orders@bookhaven.ma'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
