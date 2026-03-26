<?php

namespace Database\Seeders;

use App\Models\Stock;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Vendors
        $vendors = [
            [
                'name' => 'PT. Teknologi Maju',
                'code' => 'VND-001',
                'address' => 'Jl. Sudirman No. 123, Jakarta',
                'phone' => '021-12345678',
                'email' => 'sales@tekmaju.com',
                'contact_person' => 'Budi Santoso',
                'tax_id' => '01.234.567.8-901.000',
                'status' => 'active',
            ],
            [
                'name' => 'CV. Sumber Rezeki',
                'code' => 'VND-002',
                'address' => 'Jl. Gatot Subroto No. 45, Bandung',
                'phone' => '022-87654321',
                'email' => 'info@sumberrezeki.com',
                'contact_person' => 'Siti Nurhaliza',
                'tax_id' => '02.345.678.9-012.000',
                'status' => 'active',
            ],
            [
                'name' => 'PT. Global Supplies',
                'code' => 'VND-003',
                'address' => 'Jl. Thamrin No. 88, Jakarta',
                'phone' => '021-98765432',
                'email' => 'order@globalsupplies.com',
                'contact_person' => 'Ahmad Wijaya',
                'tax_id' => '03.456.789.0-123.000',
                'status' => 'active',
            ],
            [
                'name' => 'Toko Elektronik Jaya',
                'code' => 'VND-004',
                'address' => 'Jl. Asia Afrika No. 12, Surabaya',
                'phone' => '031-11223344',
                'email' => 'sales@elektronikjaya.com',
                'contact_person' => 'Liem Hong',
                'tax_id' => '04.567.890.1-234.000',
                'status' => 'active',
            ],
            [
                'name' => 'PT. Furniture Indah',
                'code' => 'VND-005',
                'address' => 'Jl. Raya Bogor KM 25, Depok',
                'phone' => '021-77778888',
                'email' => 'marketing@furnitureindah.com',
                'contact_person' => 'Dewi Lestari',
                'tax_id' => '05.678.901.2-345.000',
                'status' => 'active',
            ],
        ];

        foreach ($vendors as $vendor) {
            Vendor::create($vendor);
        }

        // Seed Stocks
        $stocks = [
            [
                'item_name' => 'Kertas A4',
                'specification' => '70 gram, putih',
                'category' => 'ATK',
                'quantity' => 50,
                'unit' => 'rim',
                'min_stock' => 10,
                'max_stock' => 100,
                'last_purchase_price' => 45000,
                'location' => 'Gudang A-1',
                'last_updated_at' => now(),
            ],
            [
                'item_name' => 'Pulpen Standard',
                'specification' => 'Hitam, merk Pilot',
                'category' => 'ATK',
                'quantity' => 200,
                'unit' => 'pcs',
                'min_stock' => 50,
                'max_stock' => 500,
                'last_purchase_price' => 2500,
                'location' => 'Gudang A-2',
                'last_updated_at' => now(),
            ],
            [
                'item_name' => 'Laptop ASUS',
                'specification' => 'Core i5, RAM 8GB, SSD 256GB',
                'category' => 'Elektronik',
                'quantity' => 5,
                'unit' => 'unit',
                'min_stock' => 2,
                'max_stock' => 20,
                'last_purchase_price' => 7500000,
                'location' => 'Gudang B-1',
                'last_updated_at' => now(),
            ],
            [
                'item_name' => 'Monitor LED 24 inch',
                'specification' => 'LG, Full HD, IPS',
                'category' => 'Elektronik',
                'quantity' => 8,
                'unit' => 'unit',
                'min_stock' => 3,
                'max_stock' => 15,
                'last_purchase_price' => 1800000,
                'location' => 'Gudang B-2',
                'last_updated_at' => now(),
            ],
            [
                'item_name' => 'Meja Kerja',
                'specification' => 'Kayu jati, ukuran 120x60cm',
                'category' => 'Furniture',
                'quantity' => 3,
                'unit' => 'unit',
                'min_stock' => 1,
                'max_stock' => 10,
                'last_purchase_price' => 1200000,
                'location' => 'Gudang C-1',
                'last_updated_at' => now(),
            ],
            [
                'item_name' => 'Kursi Kantor',
                'specification' => 'Ergonomis, bahan mesh',
                'category' => 'Furniture',
                'quantity' => 6,
                'unit' => 'unit',
                'min_stock' => 2,
                'max_stock' => 15,
                'last_purchase_price' => 850000,
                'location' => 'Gudang C-2',
                'last_updated_at' => now(),
            ],
            [
                'item_name' => 'Printer LaserJet',
                'specification' => 'HP LaserJet Pro, hitam putih',
                'category' => 'Elektronik',
                'quantity' => 2,
                'unit' => 'unit',
                'min_stock' => 1,
                'max_stock' => 5,
                'last_purchase_price' => 2500000,
                'location' => 'Gudang B-3',
                'last_updated_at' => now(),
            ],
            [
                'item_name' => 'Tinta Printer',
                'specification' => 'HP 85A, original',
                'category' => 'ATK',
                'quantity' => 15,
                'unit' => 'pcs',
                'min_stock' => 5,
                'max_stock' => 30,
                'last_purchase_price' => 450000,
                'location' => 'Gudang A-3',
                'last_updated_at' => now(),
            ],
        ];

        foreach ($stocks as $stock) {
            Stock::create($stock);
        }
    }
}
