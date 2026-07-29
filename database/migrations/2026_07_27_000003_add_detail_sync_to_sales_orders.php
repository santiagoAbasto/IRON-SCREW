<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('sales_orders', fn(Blueprint $table) => $table->timestamp('details_synced_at')->nullable()->after('synced_at')); }
    public function down(): void { Schema::table('sales_orders', fn(Blueprint $table) => $table->dropColumn('details_synced_at')); }
};
