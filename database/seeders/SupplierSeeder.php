<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'TechWorld Electronics',
                'contact_person' => 'John Smith',
                'email' => 'orders@techworld.com',
                'phone' => '+1-555-0123',
                'address' => '123 Tech Avenue, Silicon Valley, CA 94043',
                'is_active' => true,
            ],
            [
                'name' => 'Office Plus Supplies',
                'contact_person' => 'Sarah Johnson',
                'email' => 'sales@officeplus.com',
                'phone' => '+1-555-0456',
                'address' => '456 Business Road, New York, NY 10001',
                'is_active' => true,
            ],
            [
                'name' => 'Hardware Solutions Inc',
                'contact_person' => 'Mike Wilson',
                'email' => 'info@hardwaresolutions.com',
                'phone' => '+1-555-0789',
                'address' => '789 Industrial Blvd, Detroit, MI 48201',
                'is_active' => true,
            ],
            [
                'name' => 'Digital Software Corp',
                'contact_person' => 'Lisa Chen',
                'email' => 'licensing@digitalsoftware.com',
                'phone' => '+1-555-0321',
                'address' => '321 Software Lane, Austin, TX 78701',
                'is_active' => true,
            ],
            [
                'name' => 'Premium Furniture Co',
                'contact_person' => 'David Brown',
                'email' => 'orders@premiumfurniture.com',
                'phone' => '+1-555-0654',
                'address' => '654 Furniture Way, North Carolina, NC 27601',
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
