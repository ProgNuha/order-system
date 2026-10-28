<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product_name');
            $table->integer('stock')->default(0);
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_name');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_check CHECK (stock >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_check CHECK (price > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
