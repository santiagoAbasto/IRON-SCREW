<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const KILOGRAM_CODES = [
        '41-2-T-305ELEGR0000020',
        '41-25-T-305ELEGR0000025',
        '41-32-T-305ELEGR0000032',
        '41-4-T-305ELEGR0000040',
        '2314',
        '23316 1',
        '23316 2',
    ];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('label_unit', 10)->default('units')->after('units_bulk');
        });

        $codes = array_fill_keys(array_map(fn (string $code) => mb_strtoupper(trim($code)), self::KILOGRAM_CODES), true);
        DB::table('products')->select('id', 'code')->orderBy('id')->get()->each(function ($product) use ($codes): void {
            if (isset($codes[mb_strtoupper(trim((string) $product->code))])) {
                DB::table('products')->where('id', $product->id)->update(['label_unit' => 'kg']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('label_unit');
        });
    }
};
