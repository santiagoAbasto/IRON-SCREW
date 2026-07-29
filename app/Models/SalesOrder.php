<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model {
    protected $fillable=['contabilium_id','customer_id','number','customer','created_on','due_on','status','currency','total','integration','warehouse','notes','raw','synced_at','details_synced_at','detail_sync_status','detail_sync_error','detail_sync_attempted_at','locally_finalized_at'];
    protected function casts(): array { return ['created_on'=>'date','due_on'=>'date','total'=>'decimal:2','raw'=>'array','synced_at'=>'datetime','details_synced_at'=>'datetime','detail_sync_attempted_at'=>'datetime','locally_finalized_at'=>'datetime']; }
    public function items(): HasMany { return $this->hasMany(SalesOrderItem::class); }
}
