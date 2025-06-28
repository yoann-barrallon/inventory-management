<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Location::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Sorting
        $sortField = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination with stock counts
        $locations = $query->withCount(['stocks'])
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/Locations/Index', [
            'locations' => $locations,
            'filters' => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Inventory/Locations/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LocationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        Location::create($data);

        return redirect()
            ->route('inventory.locations.index')
            ->with('success', 'Location created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Location $location): Response
    {
        $location->load(['stocks.product', 'stockTransactions' => function ($query) {
            $query->with(['product', 'user'])
                ->orderBy('created_at', 'desc')
                ->take(10);
        }]);

        return Inertia::render('Inventory/Locations/Show', [
            'location' => $location,
            'totalStockItems' => $location->stocks()->count(),
            'totalQuantity' => $location->stocks()->sum('quantity'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Location $location): Response
    {
        return Inertia::render('Inventory/Locations/Edit', [
            'location' => $location,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        return redirect()
            ->route('inventory.locations.index')
            ->with('success', 'Location updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Location $location): RedirectResponse
    {
        // Check if location has stock items
        if ($location->stocks()->count() > 0) {
            return redirect()
                ->route('inventory.locations.index')
                ->with('error', 'Cannot delete location that has stock items. Please move all stock items first.');
        }

        $location->delete();

        return redirect()
            ->route('inventory.locations.index')
            ->with('success', 'Location deleted successfully.');
    }
}
