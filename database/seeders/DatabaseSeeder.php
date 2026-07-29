<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $all=['orders.view','orders.manage','users.manage','roles.manage','products.view','products.manage','settings.view'];
        $admin=Role::updateOrCreate(['name'=>'Administrador'],['description'=>'Acceso completo al sistema','permissions'=>$all]);
        $deposit=Role::updateOrCreate(['name'=>'Depósito'],['description'=>'Acceso a órdenes y productos','permissions'=>['orders.view','products.view']]);
        $adminPassword=env('IRON_ADMIN_PASSWORD');
        $depositPassword=env('IRON_DEPOSIT_PASSWORD');
        if(!$adminPassword||!$depositPassword) {
            throw new RuntimeException('Definí IRON_ADMIN_PASSWORD e IRON_DEPOSIT_PASSWORD en .env antes de ejecutar los seeders.');
        }
        User::updateOrCreate(['email'=>'admin@ironscrew.com'],['name'=>'Roberto Garcia','username'=>'admin','password'=>$adminPassword,'role_id'=>$admin->id,'is_active'=>true]);
        User::updateOrCreate(['email'=>'deposito@ironscrew.com'],['name'=>'Juan Pérez','username'=>'dep','password'=>$depositPassword,'role_id'=>$deposit->id,'is_active'=>true]);
    }
}
