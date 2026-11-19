<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('custom_callout', 255)->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'product_id'], 'post_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_product');
    }
};
