<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations for testing
        $this->artisan('migrate:fresh');
        
        // Seed roles and permissions for tests
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
    }
}
