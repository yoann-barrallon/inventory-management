<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Location;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class LocationService
{
    /**
     * Get paginated locations with filters.
     */
    public function getPaginatedLocations(Request $request): LengthAwarePaginator
    {
        $query = Location::query();

        // Apply search filter
        if ($request->filled('search')) {
            $this->applySearchFilter($query, $request->input('search'));
        }

        // Apply status filter
        if ($request->filled('status')) {
            $this->applyStatusFilter($query, $request->input('status'));
        }

        // Apply sorting
        $sortField = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        return $query->withSum('stocks', 'quantity')
            ->withCount('stocks')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Create a new location.
     */
    public function createLocation(array $data): Location
    {
        $data['is_active'] = $data['is_active'] ?? true;
        
        return Location::create($data);
    }

    /**
     * Update an existing location.
     */
    public function updateLocation(Location $location, array $data): bool
    {
        return $location->update($data);
    }

    /**
     * Check if location can be deleted.
     */
    public function canDeleteLocation(Location $location): bool
    {
        return $location->stocks()->count() === 0;
    }

    /**
     * Delete a location.
     */
    public function deleteLocation(Location $location): array
    {
        if (!$this->canDeleteLocation($location)) {
            return [
                'success' => false,
                'message' => 'Cannot delete location that has stock. Please move all stock first.',
            ];
        }

        $location->delete();

        return [
            'success' => true,
            'message' => 'Location deleted successfully.',
        ];
    }

    /**
     * Get location with stock information for detailed view.
     */
    public function getLocationWithStock(Location $location): Location
    {
        return $location->load([
            'stocks.product' => function ($query) {
                $query->with('category')
                      ->where('is_active', true)
                      ->orderBy('name');
            }
        ]);
    }

    /**
     * Get location statistics.
     */
    public function getLocationStatistics(Location $location): array
    {
        $stocks = $location->stocks()->with('product')->get();

        return [
            'total_products' => $stocks->count(),
            'total_stock_value' => $stocks->sum(function ($stock) {
                return $stock->quantity * $stock->product->cost_price;
            }),
            'total_quantity' => $stocks->sum('quantity'),
            'low_stock_items' => $stocks->filter(function ($stock) {
                return $stock->quantity <= $stock->product->min_stock_level;
            })->count(),
        ];
    }

    /**
     * Get locations for dropdown/select options.
     */
    public function getLocationsForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return Location::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Apply search filter to query.
     */
    private function applySearchFilter($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('address', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Apply status filter to query.
     */
    private function applyStatusFilter($query, string $status): void
    {
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
    }
}
