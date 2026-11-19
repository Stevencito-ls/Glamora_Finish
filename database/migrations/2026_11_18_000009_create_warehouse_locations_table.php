<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('zone', 60)->index();
            $table->string('aisle', 30);
            $table->string('rack', 30);
            $table->string('shelf', 30);
            $table->string('description', 255)->nullable();
            $table->boolean('temperature_controlled')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
    }
};
