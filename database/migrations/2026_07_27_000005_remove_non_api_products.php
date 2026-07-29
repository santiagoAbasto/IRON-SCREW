<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->whereNull('contabilium_id')->delete();
    }

    public function down(): void
    {
        // Los productos locales retirados no se recrean: Contabilium es la fuente única.
    }
};
