<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class TestPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the inventory permission system';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('🧪 Testing Inventory Permission System');
        $this->newLine();

        // Test users with different roles
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->first();
        $stockManager = User::whereHas('roles', fn($q) => $q->where('name', 'stock_manager'))->first();
        $operator = User::whereHas('roles', fn($q) => $q->where('name', 'operator'))->first();

        if (!$admin || !$stockManager || !$operator) {
            $this->error('❌ Test users not found. Please run: php artisan db:seed');
            return;
        }

        $this->testUser($admin, 'Admin');
        $this->newLine();
        $this->testUser($stockManager, 'Stock Manager');
        $this->newLine();
        $this->testUser($operator, 'Operator');

        $this->newLine();
        $this->info('✅ Permission testing completed!');
    }

    private function testUser(User $user, string $roleTitle): void
    {
        $this->info("👤 Testing {$roleTitle}: {$user->name}");
        $this->newLine();

        // Test basic access
        $this->testPermission($user, 'view dashboard', '📊 Dashboard Access');
        $this->testPermission($user, 'view products', '📦 View Products');
        $this->testPermission($user, 'create products', '➕ Create Products');
        $this->testPermission($user, 'edit products', '✏️ Edit Products');
        $this->testPermission($user, 'delete products', '🗑️ Delete Products');

        // Test stock permissions
        $this->testPermission($user, 'view stock', '📊 View Stock');
        $this->testPermission($user, 'manage stock', '⚙️ Manage Stock');
        $this->testPermission($user, 'create stock transactions', '📝 Create Stock Transactions');

        // Test admin permissions
        $this->testPermission($user, 'view users', '👥 View Users');
        $this->testPermission($user, 'manage roles', '🔐 Manage Roles');

        // Test role checks
        $this->testRole($user, 'admin', '👑 Admin Role');
        $this->testRole($user, 'stock_manager', '📋 Stock Manager Role');
        $this->testRole($user, 'operator', '🔧 Operator Role');
    }

    private function testPermission(User $user, string $permission, string $description): void
    {
        $hasPermission = $user->can($permission);
        $status = $hasPermission ? '✅' : '❌';
        $this->line("  {$status} {$description}");
    }

    private function testRole(User $user, string $role, string $description): void
    {
        $hasRole = $user->hasRole($role);
        $status = $hasRole ? '✅' : '❌';
        $this->line("  {$status} {$description}");
    }
}
