<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('payment_method', 30)->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('PEN');
            $table->string('status', 30)->default('pending')->index();
            $table->string('transaction_reference', 120)->nullable()->index();
            $table->string('voucher_path', 255)->nullable();
            $table->string('crypto_network', 50)->nullable();
            $table->string('crypto_tx_hash', 150)->nullable()->index();
            $table->date('b2b_due_date')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
