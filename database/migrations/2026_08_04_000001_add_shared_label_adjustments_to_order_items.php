<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->string('label_type', 20)->nullable()->after('raw');
            $table->decimal('label_units', 15, 3)->nullable()->after('label_type');
            $table->unsignedInteger('label_count')->nullable()->after('label_units');
            $table->boolean('label_allow_overage')->default(false)->after('label_count');
            $table->foreignId('label_adjusted_by')->nullable()->after('label_allow_overage')->constrained('users')->nullOnDelete();
            $table->timestamp('label_adjusted_at')->nullable()->after('label_adjusted_by');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('label_adjusted_by');
            $table->dropColumn(['label_type', 'label_units', 'label_count', 'label_allow_overage', 'label_adjusted_at']);
        });
    }
};
