<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('units_fractioned', 12, 3)->unsigned()->default(0)->change();
            $table->decimal('units_fractioned_x100', 12, 3)->unsigned()->default(0)->change();
            $table->decimal('units_bulk', 12, 3)->unsigned()->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('units_fractioned')->default(0)->change();
            $table->unsignedInteger('units_fractioned_x100')->default(0)->change();
            $table->unsignedInteger('units_bulk')->default(0)->change();
        });
    }
};
