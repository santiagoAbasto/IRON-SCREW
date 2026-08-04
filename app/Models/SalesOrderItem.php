<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model {
    protected $fillable=['sales_order_id','contabilium_concept_id','code','description','quantity','unit_price','tax','raw','label_type','label_units','label_count','label_allow_overage','label_adjusted_by','label_adjusted_at'];
    protected function casts(): array { return ['quantity'=>'decimal:3','unit_price'=>'decimal:2','tax'=>'decimal:3','raw'=>'array','label_units'=>'decimal:3','label_allow_overage'=>'boolean','label_adjusted_at'=>'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class,'sales_order_id'); }
    public function adjustedBy(): BelongsTo { return $this->belongsTo(User::class,'label_adjusted_by'); }
}
