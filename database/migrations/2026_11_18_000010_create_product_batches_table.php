<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->string('batch_number', 80)->unique();
            $table->date('manufacturing_date')->nullable();
            $table->date('expiration_date')->index();
            $table->unsignedInteger('initial_quantity');
            $table->unsignedInteger('current_quantity')->index();
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->string('supplier_name', 150)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_variant_id', 'status', 'expiration_date'], 'batches_fefo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
