<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_orders')
            ->whereNull('locally_finalized_at')
            ->update(['status' => 'Pendiente']);
    }

    public function down(): void
    {
        // El estado anterior provenía de Contabilium y no debe restaurarse.
    }
};
