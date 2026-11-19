<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->constrained('brands')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('slug', 150)->unique();
            $table->string('short_description', 300)->nullable();
            $table->longText('description')->nullable();
            $table->string('sanitary_registration', 100)->nullable()->index();
            $table->text('ingredients')->nullable();
            $table->text('how_to_use')->nullable();
            $table->string('skin_type', 100)->nullable();
            $table->string('finish_type', 80)->nullable();
            $table->unsignedInteger('min_stock_alert')->default(10);
            $table->unsignedInteger('reorder_point')->default(20);
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_new_launch')->default(false)->index();
            $table->boolean('is_b2b_kit')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('meta_title', 180)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('main_image', 255)->nullable();
            $table->json('gallery')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
