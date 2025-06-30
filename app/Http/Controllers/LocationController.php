<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\GenericFilterDto;
use App\Http\Requests\LocationRequest;
use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function __construct(
        private readonly LocationService $locationService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $filters = GenericFilterDto::fromArray($request->all());
        $locations = $this->locationService->getPaginatedLocations($filters);

        return Inertia::render('Inventory/Locations/Index', [
            'locations' => $locations,
            'filters' => $filters->toArray(),
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
        $this->locationService->createLocation($request->validated());

        return redirect()
            ->route('inventory.locations.index')
            ->with('success', 'Location created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Location $location): Response
    {
        $location = $this->locationService->getLocationWithStock($location);
        $statistics = $this->locationService->getLocationStatistics($location);

        return Inertia::render('Inventory/Locations/Show', [
            'location' => $location,
            'statistics' => $statistics,
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
        $this->locationService->updateLocation($location, $request->validated());

        return redirect()
            ->route('inventory.locations.index')
            ->with('success', 'Location updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Location $location): RedirectResponse
    {
        $result = $this->locationService->deleteLocation($location);

        $redirectResponse = redirect()->route('inventory.locations.index');

        if ($result['success']) {
            return $redirectResponse->with('success', $result['message']);
        }

        return $redirectResponse->with('error', $result['message']);
    }
}
