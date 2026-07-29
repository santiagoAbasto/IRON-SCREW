<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Product extends Model {
    protected $fillable=['contabilium_id','code','description','barcode','price','stock','units_fractioned','units_fractioned_x100','units_bulk','is_active','synced_at'];
    protected function casts(): array { return ['is_active'=>'boolean','price'=>'decimal:2','stock'=>'decimal:3','synced_at'=>'datetime']; }
}
