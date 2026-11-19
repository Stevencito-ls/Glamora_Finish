<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_carts', function (Blueprint $table): void {
            $table->id();
            $table->string('session_id', 120)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 150)->nullable()->index();
            $table->json('cart_payload');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->unsignedInteger('items_count')->default(0);
            $table->timestamp('last_activity_at')->index();
            $table->unsignedSmallInteger('reminder_count')->default(0);
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamp('recovered_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_carts');
    }
};
