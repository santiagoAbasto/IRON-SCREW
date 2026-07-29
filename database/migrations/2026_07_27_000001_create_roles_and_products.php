<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('roles', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->string('description')->nullable(); $table->json('permissions'); $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name'); $table->foreignId('role_id')->nullable()->after('password')->constrained()->nullOnDelete(); $table->boolean('is_active')->default(true)->after('role_id');
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->string('description'); $table->unsignedInteger('units_fractioned')->default(0); $table->unsignedInteger('units_bulk')->default(0); $table->boolean('is_active')->default(true); $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('products');
        Schema::table('users', function (Blueprint $table) { $table->dropForeign(['role_id']); $table->dropColumn(['username','role_id','is_active']); });
        Schema::dropIfExists('roles');
    }
};
