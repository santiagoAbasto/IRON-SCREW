<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Product extends Model {
    public const KILOGRAM_CODES=['41-2-T-305ELEGR0000020','41-25-T-305ELEGR0000025','41-32-T-305ELEGR0000032','41-4-T-305ELEGR0000040','2314','23316 1','23316 2'];
    protected $fillable=['contabilium_id','code','description','barcode','price','stock','units_fractioned','units_fractioned_x100','units_bulk','label_unit','label_exact_order','is_active','synced_at'];
    protected function casts(): array { return ['label_exact_order'=>'boolean','is_active'=>'boolean','price'=>'decimal:2','stock'=>'decimal:3','synced_at'=>'datetime']; }
    public static function usesKilogramsByDefault(string $code): bool { return in_array(mb_strtoupper(trim($code)),self::KILOGRAM_CODES,true); }
    public function labelUnitText(): string { return $this->label_unit==='kg'?'KG':'UNIDADES'; }
}
