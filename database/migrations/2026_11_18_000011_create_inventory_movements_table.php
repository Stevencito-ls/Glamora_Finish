<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 40)->index();
            $table->integer('quantity');
            $table->unsignedInteger('balance_after');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'inv_movements_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
