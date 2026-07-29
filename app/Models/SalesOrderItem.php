<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model {
    protected $fillable=['sales_order_id','contabilium_concept_id','code','description','quantity','unit_price','tax','raw'];
    protected function casts(): array { return ['quantity'=>'decimal:3','unit_price'=>'decimal:2','tax'=>'decimal:3','raw'=>'array']; }
    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class,'sales_order_id'); }
}
