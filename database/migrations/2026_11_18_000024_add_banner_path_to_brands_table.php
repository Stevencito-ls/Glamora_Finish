<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            if (! Schema::hasColumn('brands', 'banner_path')) {
                $table->string('banner_path', 255)->nullable()->after('logo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            if (Schema::hasColumn('brands', 'banner_path')) {
                $table->dropColumn('banner_path');
            }
        });
    }
};
