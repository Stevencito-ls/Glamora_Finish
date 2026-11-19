<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('company_name', 180);
            $table->string('trade_name', 180)->nullable();
            $table->string('ruc', 20)->unique();
            $table->string('business_type', 80)->nullable();
            $table->string('contact_name', 150);
            $table->string('contact_phone', 25);
            $table->string('contact_email', 150);
            $table->string('fiscal_address', 255);
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->default('Lima');
            $table->string('requested_tier', 30)->default('bronce');
            $table->string('approved_tier', 30)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('credit_used', 12, 2)->default(0);
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_profiles');
    }
};
