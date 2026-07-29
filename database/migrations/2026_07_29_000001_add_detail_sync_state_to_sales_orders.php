<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('detail_sync_status', 20)->nullable()->after('details_synced_at');
            $table->text('detail_sync_error')->nullable()->after('detail_sync_status');
            $table->timestamp('detail_sync_attempted_at')->nullable()->after('detail_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['detail_sync_status', 'detail_sync_error', 'detail_sync_attempted_at']);
        });
    }
};
