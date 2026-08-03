<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use ZipArchive;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_products_users_roles_and_permissions(): void
    {
        $role = Role::create(['name' => 'Administrador', 'description' => 'Total', 'permissions' => ['settings.view', 'users.manage', 'roles.manage', 'products.view', 'products.manage']]);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $session = ['iron_user' => $admin->id, 'iron_role' => $role->id];

        $this->withSession($session)->post(route('settings.roles.store'), ['name' => 'Operador', 'permissions' => ['products.view']])->assertRedirect();
        $operator = Role::where('name', 'Operador')->firstOrFail();
        $this->withSession($session)->post(route('settings.users.store'), ['name' => 'Operador Uno', 'username' => 'operador', 'email' => 'operador@example.com', 'password' => 'ClaveSegura123', 'role_id' => $operator->id, 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('users', ['username' => 'operador', 'role_id' => $operator->id]);
    }

    public function test_admin_cannot_delete_the_current_user(): void
    {
        $role = Role::create(['name' => 'Administrador', 'permissions' => ['settings.view', 'users.manage']]);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->withSession(['iron_user' => $admin->id])
            ->from(route('settings.users'))
            ->delete(route('settings.users.destroy', $admin))
            ->assertRedirect(route('settings.users'))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_permissions_are_enforced(): void
    {
        $role = Role::create(['name' => 'Consulta', 'permissions' => ['products.view']]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        Product::create(['contabilium_id' => 99, 'code' => 'DEPOSITO-1', 'description' => 'Producto ingresante', 'units_fractioned' => 5, 'units_bulk' => 10, 'is_active' => true]);
        $this->withSession(['iron_user' => $user->id])->get(route('settings.products'))
            ->assertOk()
            ->assertSee('Productos')
            ->assertSee('Imprimir etiquetas')
            ->assertSee(route('settings.products'), false)
            ->assertDontSee('Editar producto')
            ->assertDontSee('Subir Excel')
            ->assertDontSee('import-products', false);
        $this->withSession(['iron_user' => $user->id])->get(route('settings.products.bulk-template'))->assertForbidden();
    }

    public function test_admin_can_download_template_and_import_bulk_quantities(): void
    {
        $role = Role::create(['name' => 'Administrador', 'permissions' => ['settings.view', 'products.view', 'products.manage']]);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $session = ['iron_user' => $admin->id];
        $product = Product::create(['contabilium_id' => 1, 'code' => 'SKU-1', 'description' => 'Producto uno', 'units_fractioned' => 0, 'units_bulk' => 20, 'is_active' => true]);

        $response = $this->withSession($session)->get(route('settings.products.bulk-template'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'bulk_test_');
        copy($path, $copy);

        $zip = new ZipArchive;
        $zip->open($copy);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheet = str_replace('<c r="D2" s="2"><v>20</v></c>', '<c r="D2" s="2"><v>45</v></c>', $sheet);
        $sheet = str_replace('<c r="C2" s="0" t="inlineStr"><is><t></t></is></c>', '<c r="C2" s="2"><v>12</v></c>', $sheet);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $file = new UploadedFile($copy, 'cantidades.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $this->withSession($session)->post(route('settings.products.bulk-import'), ['file' => $file])
            ->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('import_report', fn (array $report) => count($report['new']) === 1 && count($report['changed']) === 1);
        $this->assertSame(45, $product->fresh()->units_bulk);
        $this->assertSame(12, $product->fresh()->units_fractioned);
        $this->assertSame(0, $product->fresh()->units_fractioned_x100);
    }

    public function test_original_zebra_file_uses_fraction_x100_over_fraction_1_and_ignores_fraction_2(): void
    {
        $role = Role::create(['name' => 'Administrador', 'permissions' => ['settings.view', 'products.view', 'products.manage']]);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $session = ['iron_user' => $admin->id];
        $product = Product::create(['contabilium_id' => 142, 'code' => '111-142 21', 'description' => 'HEXA AGUJA CH/G 14 X 2', 'units_fractioned' => 0, 'units_bulk' => 0, 'is_active' => true]);

        $response = $this->withSession($session)->get(route('settings.products.bulk-template'))->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'zebra_test_');
        copy($path, $copy);

        $zip = new ZipArchive;
        $zip->open($copy);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheetData = '<sheetData>'
            .'<row r="1"><c r="A1" t="inlineStr"><is><t>CODIGO</t></is></c><c r="B1" t="inlineStr"><is><t>CANTIDAD POR CAJA</t></is></c><c r="C1" t="inlineStr"><is><t>FRACCIÓN 1</t></is></c><c r="D1" t="inlineStr"><is><t>FRACCIÓN 2</t></is></c><c r="E1" t="inlineStr"><is><t>FRACCIÓN x 100</t></is></c><c r="F1" t="inlineStr"><is><t>DESCRIPCION</t></is></c></row>'
            .'<row r="2"><c r="A2" t="inlineStr"><is><t>111-142 21</t></is></c><c r="B2"><v>1000</v></c><c r="C2"><v>500</v></c><c r="D2"><v>999</v></c><c r="E2"><v>1200</v></c><c r="F2" t="inlineStr"><is><t>HEXA AGUJA CH/G 14 X 2</t></is></c></row>'
            .'</sheetData>';
        $sheet = preg_replace('/<sheetData>.*<\/sheetData>/s', $sheetData, $sheet);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $file = new UploadedFile($copy, 'lista-productos-zebra.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $this->withSession($session)->post(route('settings.products.bulk-import'), ['file' => $file])
            ->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(1200, $product->units_fractioned);
        $this->assertSame(0, $product->units_fractioned_x100);
        $this->assertSame(1000, $product->units_bulk);
    }

    public function test_admin_can_edit_and_delete_an_api_product_but_cannot_create_one_manually(): void
    {
        $role = Role::create(['name' => 'Administrador', 'permissions' => ['settings.view', 'products.view', 'products.manage']]);
        $admin = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $session = ['iron_user' => $admin->id];
        $product = Product::create(['contabilium_id' => 99, 'code' => 'API-1', 'description' => 'Producto API', 'units_fractioned' => 10, 'units_bulk' => 20, 'is_active' => true]);

        $this->withSession($session)->put(route('settings.products.update', $product), [
            'code' => 'API-1', 'description' => 'Producto API editado', 'units_fractioned' => 15, 'units_bulk' => 30, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'description' => 'Producto API editado', 'units_fractioned' => 15, 'units_fractioned_x100' => 0, 'units_bulk' => 30]);

        $this->withSession($session)->put(route('settings.products.packaging', $product), [
            'units_fractioned' => '', 'units_bulk' => 35,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'units_fractioned' => 0, 'units_fractioned_x100' => 0, 'units_bulk' => 35]);

        $this->withSession($session)->delete(route('settings.products.destroy', $product))->assertRedirect();
        $this->assertDatabaseMissing('products',['id' => $product->id]);
        $this->assertFalse(Route::has('settings.products.store'));
    }
}
