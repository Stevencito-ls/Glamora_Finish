<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->string('tracking_code', 64)->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name', 150);
            $table->string('customer_email', 150)->index();
            $table->string('customer_phone', 25)->nullable();
            $table->string('document_type', 20)->default('DNI');
            $table->string('document_number', 20)->nullable();
            $table->string('shipping_address', 255);
            $table->string('shipping_district', 100);
            $table->string('shipping_city', 100)->default('Lima');
            $table->string('shipping_method', 30)->default('estandar');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->string('customer_tier', 30)->default('minorista');
            $table->string('status', 30)->default('pendiente_pago')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('coupon_code', 50)->nullable()->index();
            $table->decimal('tax_igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->boolean('is_b2b')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamp('fefo_dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
