<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\UserFilterDto;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserService
{
    /**
     * Get paginated users with filters.
     */
    public function getPaginatedUsers(UserFilterDto $filters): LengthAwarePaginator
    {
        $query = User::with('roles');

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($filters->perPage);
    }

    /**
     * Create a new user.
     */
    public function createUser(array $data): User
    {
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ];

        $user = User::create($userData);

        // Assign roles if provided
        if (isset($data['roles']) && is_array($data['roles'])) {
            $user->assignRole($data['roles']);
        }

        return $user->load('roles');
    }

    /**
     * Update an existing user.
     */
    public function updateUser(User $user, array $data): bool
    {
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        // Update password only if provided
        if (isset($data['password']) && !empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }

        $updated = $user->update($userData);

        // Update roles if provided
        if (isset($data['roles']) && is_array($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $updated;
    }

    /**
     * Check if user can be deleted.
     */
    public function canDeleteUser(User $user): bool
    {
        // Prevent deletion of users with stock transactions
        return $user->stockTransactions()->count() === 0 &&
               $user->purchaseOrders()->count() === 0;
    }

    /**
     * Delete a user.
     */
    public function deleteUser(User $user): array
    {
        if (!$this->canDeleteUser($user)) {
            return [
                'success' => false,
                'message' => 'Cannot delete user with existing transactions or purchase orders.',
            ];
        }

        $user->delete();

        return [
            'success' => true,
            'message' => 'User deleted successfully.',
        ];
    }

    /**
     * Get user with activity statistics.
     */
    public function getUserWithStats(User $user): array
    {
        $user->load('roles');

        return [
            'user' => $user,
            'stats' => [
                'stock_transactions' => $user->stockTransactions()->count(),
                'purchase_orders' => $user->purchaseOrders()->count(),
                'recent_transactions' => $user->stockTransactions()
                    ->with(['product', 'location'])
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get(),
                'recent_purchase_orders' => $user->purchaseOrders()
                    ->with('supplier')
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get(),
            ],
        ];
    }

    /**
     * Get form data for user forms.
     */
    public function getFormData(): array
    {
        return [
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Assign role to user.
     */
    public function assignRole(User $user, string $roleName): bool
    {
        if ($user->hasRole($roleName)) {
            return false; // User already has this role
        }

        $user->assignRole($roleName);
        return true;
    }

    /**
     * Remove role from user.
     */
    public function removeRole(User $user, string $roleName): bool
    {
        if (!$user->hasRole($roleName)) {
            return false; // User doesn't have this role
        }

        $user->removeRole($roleName);
        return true;
    }

    /**
     * Get users by role.
     */
    public function getUsersByRole(string $roleName)
    {
        return User::role($roleName)
            ->orderBy('name')
            ->get();
    }

    /**
     * Search users for selection/autocomplete.
     */
    public function searchUsers(string $query, int $limit = 10)
    {
        return User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orderBy('name')
            ->take($limit)
            ->get(['id', 'name', 'email']);
    }

    /**
     * Get user activity statistics.
     */
    public function getUserActivity(User $user, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        return [
            'transactions_count' => $user->stockTransactions()
                ->where('created_at', '>=', $startDate)
                ->count(),
            'purchase_orders_count' => $user->purchaseOrders()
                ->where('created_at', '>=', $startDate)
                ->count(),
            'last_login' => $user->last_login_at ?? null,
            'total_transactions' => $user->stockTransactions()->count(),
            'total_purchase_orders' => $user->purchaseOrders()->count(),
        ];
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, UserFilterDto $filters): void
    {
        // Search filter
        if ($filters->hasSearch()) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters->search}%")
                  ->orWhere('email', 'like', "%{$filters->search}%");
            });
        }

        // Role filter
        if ($filters->hasRole()) {
            $query->role($filters->role);
        }

        // Status filter
        if ($filters->hasStatus()) {
            if ($filters->status === 'active') {
                $query->whereNotNull('email_verified_at');
            } elseif ($filters->status === 'inactive') {
                $query->whereNull('email_verified_at');
            }
        }
    }

    /**
     * Apply sorting to the query.
     */
    private function applySorting($query, UserFilterDto $filters): void
    {
        $query->orderBy($filters->sortBy, $filters->sortDirection);
    }
}
