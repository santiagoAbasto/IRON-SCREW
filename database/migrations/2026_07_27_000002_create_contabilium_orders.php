<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('contabilium_id')->nullable()->unique()->after('id');
            $table->string('barcode')->nullable()->after('description');
            $table->decimal('price',15,2)->nullable()->after('barcode');
            $table->decimal('stock',15,3)->nullable()->after('price');
            $table->timestamp('synced_at')->nullable();
        });
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('contabilium_id')->unique(); $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('number')->index(); $table->string('customer'); $table->date('created_on')->nullable(); $table->date('due_on')->nullable();
            $table->string('status')->nullable(); $table->string('currency',10)->nullable(); $table->decimal('total',15,2)->nullable();
            $table->string('integration')->nullable(); $table->string('warehouse')->nullable(); $table->text('notes')->nullable(); $table->json('raw')->nullable(); $table->timestamp('synced_at')->nullable(); $table->timestamps();
        });
        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete(); $table->unsignedBigInteger('contabilium_concept_id')->nullable();
            $table->string('code')->nullable()->index(); $table->string('description'); $table->decimal('quantity',15,3); $table->decimal('unit_price',15,2)->nullable(); $table->decimal('tax',8,3)->nullable(); $table->json('raw')->nullable(); $table->timestamps();
        });
        Schema::create('contabilium_sync_logs', function (Blueprint $table) {
            $table->id(); $table->string('resource'); $table->string('status'); $table->unsignedInteger('records')->default(0); $table->text('message')->nullable(); $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('contabilium_sync_logs'); Schema::dropIfExists('sales_order_items'); Schema::dropIfExists('sales_orders');
        Schema::table('products', fn(Blueprint $table) => $table->dropColumn(['contabilium_id','barcode','price','stock','synced_at']));
    }
};
