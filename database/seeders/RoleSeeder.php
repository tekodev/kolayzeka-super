<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Create Admin User
        $admin = User::firstOrCreate([
            'email' => 'mehtap@kolayzeka.com',
        ], [
            'name' => 'Mehtap Tekin',
            'password' => bcrypt('123123'),
            'credit_balance' => 999999,
        ]);
        
        $admin->assignRole($adminRole);
    }
}
