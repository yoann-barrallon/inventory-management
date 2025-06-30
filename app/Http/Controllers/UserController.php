<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\UserFilterDto;
use App\Http\Requests\UserRequest;
use App\Http\Requests\UserRoleRequest;
use App\Http\Requests\UserProfileRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Display a listing of users.
     */
    public function index(Request $request): Response
    {
        $filters = UserFilterDto::fromArray($request->all());
        $users = $this->userService->getPaginatedUsers($filters);
        $formData = $this->userService->getFormData();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $formData['roles'],
            'filters' => $filters->toArray(),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        $formData = $this->userService->getFormData();

        return Inertia::render('Admin/Users/Create', $formData);
    }

    /**
     * Store a newly created user.
     */
    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->userService->createUser($request->validated());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): Response
    {
        $userData = $this->userService->getUserWithStats($user);

        return Inertia::render('Admin/Users/Show', $userData);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        $formData = $this->userService->getFormData();

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('roles'),
            ...$formData,
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->userService->updateUser($user, $request->validated());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $result = $this->userService->deleteUser($user);

        $redirectResponse = redirect()->route('admin.users.index');

        if ($result['success']) {
            return $redirectResponse->with('success', $result['message']);
        }

        return $redirectResponse->with('error', $result['message']);
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(UserRoleRequest $request, User $user): RedirectResponse
    {
        $assigned = $this->userService->assignRole($user, $request->input('role'));

        $redirectResponse = redirect()->route('admin.users.show', $user);

        if ($assigned) {
            return $redirectResponse->with('success', 'Role assigned successfully.');
        }

        return $redirectResponse->with('info', 'User already has this role.');
    }

    /**
     * Remove a role from a user.
     */
    public function removeRole(UserRoleRequest $request, User $user): RedirectResponse
    {
        $removed = $this->userService->removeRole($user, $request->input('role'));

        $redirectResponse = redirect()->route('admin.users.show', $user);

        if ($removed) {
            return $redirectResponse->with('success', 'Role removed successfully.');
        }

        return $redirectResponse->with('info', 'User does not have this role.');
    }

    /**
     * Get users by role (AJAX endpoint).
     */
    public function getUsersByRole(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $users = $this->userService->getUsersByRole($request->input('role'));

        return response()->json($users);
    }

    /**
     * Search users for autocomplete (AJAX endpoint).
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $users = $this->userService->searchUsers(
            $request->input('query'),
            $request->integer('limit', 10)
        );

        return response()->json($users);
    }

    /**
     * Get user activity statistics (AJAX endpoint).
     */
    public function getActivity(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $activity = $this->userService->getUserActivity(
            $user,
            $request->integer('days', 30)
        );

        return response()->json($activity);
    }

    /**
     * Show user profile page.
     */
    public function profile(): Response
    {
        $user = auth()->user();
        $userData = $this->userService->getUserWithStats($user);

        return Inertia::render('Profile/Show', $userData);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(UserProfileRequest $request): RedirectResponse
    {
        $data = $request->only(['name', 'email']);
        
        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        $this->userService->updateUser(auth()->user(), $data);

        return redirect()
            ->route('profile.show')
            ->with('success', 'Profile updated successfully.');
    }
}
