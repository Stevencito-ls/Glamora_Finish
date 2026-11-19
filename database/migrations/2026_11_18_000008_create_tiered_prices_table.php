<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiered_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('customer_tier', 30)->index();
            $table->unsignedInteger('min_quantity');
            $table->unsignedInteger('max_quantity')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_variant_id', 'customer_tier', 'min_quantity'], 'tiered_prices_variant_tier_min_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiered_prices');
    }
};
