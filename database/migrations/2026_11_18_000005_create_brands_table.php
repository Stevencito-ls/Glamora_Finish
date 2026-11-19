<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 150)->unique();
            $table->string('country_origin', 80)->default('Perú');
            $table->text('description')->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('website_url', 255)->nullable();
            $table->boolean('is_cruelty_free')->default(true);
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
