<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_orders')->whereRaw('LOWER(TRIM(status)) = ?', ['pendiente'])->update(['status' => 'Nueva']);
    }

    public function down(): void
    {
        DB::table('sales_orders')->where('status', 'Nueva')->update(['status' => 'Pendiente']);
    }
};
