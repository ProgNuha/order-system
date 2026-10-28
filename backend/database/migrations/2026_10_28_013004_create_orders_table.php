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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignUuid('customer_id')->constrained('customers');
            $table->foreignId('created_by')->constrained('users');
            $table->enum('status', ['PENDING', 'PAID', 'SHIPPED', 'COMPLETED', 'CANCELLED'])
                ->default('PENDING');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_check CHECK (total_amount >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
