<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** superadmin: everything · admin: back office without Settings · cashier: the till. */
class RolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function as(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    public function test_each_role_reaches_exactly_its_screens(): void
    {
        $backOffice = ['admin.reports', 'admin.customers.index', 'admin.inventory.index', 'admin.products.index', 'admin.categories.index'];
        $settings = ['admin.settings.edit', 'admin.users.index', 'admin.users.create', 'admin.zatca.edit', 'admin.invoices.index', 'admin.invoices.create'];

        $expect = [
            'superadmin' => ['backOffice' => 200, 'settings' => 200],
            'admin' => ['backOffice' => 200, 'settings' => 403],
            'cashier' => ['backOffice' => 403, 'settings' => 403],
        ];

        foreach ($expect as $role => $codes) {
            $user = $this->as($role);
            $this->actingAs($user)->get(route('pos'))->assertOk();
            $this->actingAs($user)->get(route('orders.index'))->assertOk();
            foreach ($backOffice as $name) {
                $this->actingAs($user)->get(route($name))->assertStatus($codes['backOffice']);
            }
            foreach ($settings as $name) {
                $this->actingAs($user)->get(route($name))->assertStatus($codes['settings']);
            }
        }
    }

    public function test_admin_cannot_change_settings_users_or_zatca(): void
    {
        $admin = $this->as('admin');

        $this->actingAs($admin)->put(route('admin.settings.update'), ['store_name_en' => 'X'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@pos.test', 'role' => 'superadmin',
            'password' => 'secret12', 'password_confirmation' => 'secret12',
        ])->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $this->as('superadmin')))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.zatca.csr'))->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@pos.test']);
        $this->assertDatabaseHas('users', ['email' => 'superadmin@pos.test']);
    }

    public function test_superadmin_can_create_every_role(): void
    {
        foreach (User::ROLES as $role) {
            $this->actingAs($this->as('superadmin'))->post(route('admin.users.store'), [
                'name' => "New $role", 'email' => "new-$role@pos.test", 'role' => $role,
                'password' => 'secret12', 'password_confirmation' => 'secret12', 'is_active' => 1,
            ])->assertRedirect(route('admin.users.index'));
            $this->assertDatabaseHas('users', ['email' => "new-$role@pos.test", 'role' => $role]);
        }
    }

    public function test_role_flags_are_shared_with_the_ui(): void
    {
        $flags = ['superadmin' => [true, true], 'admin' => [true, false], 'cashier' => [false, false]];

        foreach ($flags as $role => [$isAdmin, $isSuper]) {
            $this->actingAs($this->as($role))->get(route('pos'))->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.role', $role)
                ->where('auth.user.is_admin', $isAdmin)
                ->where('auth.user.is_superadmin', $isSuper)
                ->etc());
        }
    }

    public function test_existing_install_gets_its_first_admin_promoted_once(): void
    {
        $migration = require database_path('migrations/2026_11_17_000001_promote_first_admin_to_superadmin.php');

        // An install from before this role existed: two admins, no superadmin.
        User::where('role', 'superadmin')->delete();
        $first = $this->as('admin');
        $second = User::create(['name' => 'Admin 2', 'email' => 'a2@pos.test', 'password' => 'x', 'role' => 'admin', 'is_active' => true]);

        $migration->up();
        $this->assertSame('superadmin', $first->fresh()->role);
        $this->assertSame('admin', $second->fresh()->role);

        // Running again changes nothing: a superadmin already exists.
        $migration->up();
        $this->assertSame('admin', $second->fresh()->role);
    }
}
