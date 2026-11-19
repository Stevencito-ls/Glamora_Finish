<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 25)->nullable()->after('password');
            $table->string('document_type', 20)->nullable()->default('DNI')->after('phone');
            $table->string('document_number', 20)->nullable()->index()->after('document_type');
            $table->string('customer_tier', 30)->default('minorista')->index()->after('document_number');
            $table->string('default_address', 255)->nullable()->after('customer_tier');
            $table->string('district', 100)->nullable()->after('default_address');
            $table->string('city', 100)->nullable()->default('Lima')->after('district');
            $table->boolean('two_factor_enabled')->default(false)->after('city');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->boolean('is_active')->default(true)->index()->after('two_factor_confirmed_at');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['document_number']);
            $table->dropIndex(['customer_tier']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'phone',
                'document_type',
                'document_number',
                'customer_tier',
                'default_address',
                'district',
                'city',
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};
