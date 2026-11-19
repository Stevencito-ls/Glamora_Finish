<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 80)->unique();
            $table->string('shade_name', 120);
            $table->string('hex_color', 20)->nullable();
            $table->decimal('weight_grams', 8, 2)->nullable();
            $table->decimal('volume_ml', 8, 2)->nullable();
            $table->decimal('base_price', 10, 2);
            $table->decimal('wholesale_base_price', 10, 2);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->string('barcode', 80)->nullable()->unique();
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
