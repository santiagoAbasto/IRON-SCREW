<?php

namespace Tests\Feature;

use App\Jobs\SyncContabiliumJob;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ContabiliumSyncQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_sync_is_queued_without_waiting_for_the_api(): void
    {
        Queue::fake();
        Process::fake();
        $role = Role::create([
            'name' => 'Administrador',
            'permissions' => ['orders.view', 'orders.manage'],
        ]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->withSession(['iron_user' => $user->id])
            ->from(route('orders.index'))
            ->post(route('orders.sync'))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('success');

        Queue::assertPushed(SyncContabiliumJob::class, 1);
        Process::assertRan(fn($process)=>str_contains((string)$process->command,'queue:work --stop-when-empty'));
    }
}
