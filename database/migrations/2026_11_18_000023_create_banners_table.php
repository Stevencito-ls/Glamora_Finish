<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->text('subtitle')->nullable();
            $table->string('eyebrow', 100)->nullable();
            $table->string('badge_text', 120)->nullable();
            $table->string('button_text', 80)->nullable();
            $table->string('button_url', 255)->nullable();
            $table->string('secondary_button_text', 80)->nullable();
            $table->string('secondary_button_url', 255)->nullable();
            $table->string('image_path', 255);
            $table->string('accent_image_path', 255)->nullable();
            $table->string('position', 40)->default('hero_principal')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
