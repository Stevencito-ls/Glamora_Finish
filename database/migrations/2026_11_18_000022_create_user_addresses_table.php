<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 60)->default('Casa');
            $table->string('recipient_name', 150);
            $table->string('phone', 25);
            $table->string('address_line', 255);
            $table->string('district', 100);
            $table->string('city', 100)->default('Lima');
            $table->string('reference', 255)->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
