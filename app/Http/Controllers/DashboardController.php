<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /**
     * Display the main inventory dashboard.
     */
    public function index(): Response
    {
        $dashboardData = $this->dashboardService->getDashboardStats();

        return Inertia::render('Dashboard', $dashboardData);
    }
}
