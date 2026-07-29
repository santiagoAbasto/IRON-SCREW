<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->orderBy('id')->each(function (object $role): void {
            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];
            $permissions = array_values(array_diff($permissions, ['picking.view', 'picking.manage']));

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Picking fue retirado del sistema; sus permisos no se restauran.
    }
};
