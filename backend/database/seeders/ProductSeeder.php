<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        Product::factory()
            ->count(50)
            ->create()
            ->each(function (Product $product) use ($admin) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'INITIAL',
                    'quantity_change' => $product->stock,
                    'stock_after' => $product->stock,
                    'reference_type' => null,
                    'reference_id' => null,
                    'note' => 'Initial stock from seeder',
                    'created_by' => $admin->id,
                ]);
            });
    }
}