# 📅 Development Phases – Backend & Frontend with Laravel 12, React & Inertia.js

## Phase 1: Backend Development (Laravel 12) – Weeks 3 to 7

### 🗄️ Database & Models (Week 3) ✅ COMPLETED

- ✅ Create Laravel migrations for all predefined tables:

    - ✅ `categories` - name, description, slug
    - ✅ `locations` - name, description, address, active status
    - ✅ `suppliers` - complete supplier information
    - ✅ `products` - SKU, barcode, pricing, relationships
    - ✅ `stocks` - quantities per product/location
    - ✅ `stock_transactions` - movement traceability
    - ✅ `purchase_orders` - purchase orders
    - ✅ `purchase_order_details` - order details
    - ✅ `permissions` tables (spatie/laravel-permission)

- ✅ Develop Eloquent models with appropriate relationships:

    - ✅ `Product` ↔ `Category`, `Supplier`, `Stock`, `StockTransaction`, `PurchaseOrderDetail`
    - ✅ `Category` ↔ `Product` (One-to-Many)
    - ✅ `Stock` ↔ `Product`, `Location` with calculated attributes
    - ✅ `Location` ↔ `Stock`, `StockTransaction` (One-to-Many)
    - ✅ `StockTransaction` ↔ `Product`, `Location`, `User`
    - ✅ `Supplier` ↔ `Product`, `PurchaseOrder` (One-to-Many)
    - ✅ `PurchaseOrder` ↔ `Supplier`, `User`, `PurchaseOrderDetail` + generateOrderNumber()
    - ✅ `PurchaseOrderDetail` ↔ `PurchaseOrder`, `Product` with calculated attributes
    - ✅ `User` with HasRoles trait + relations to `StockTransaction`, `PurchaseOrder`

- ✅ Create seeders to populate the database with sample/test data:
    - ✅ `RoleAndPermissionSeeder` - 3 roles (admin, stock_manager, operator) + 25 permissions
    - ✅ `CategorySeeder` - 5 categories (Electronics, Office, Hardware, Software, Furniture)
    - ✅ `LocationSeeder` - 4 locations (Main Warehouse, Secondary, Office, Damaged)
    - ✅ `SupplierSeeder` - 5 suppliers with complete contact information
    - ✅ `ProductSeeder` - 9 diverse products with SKU/barcodes
    - ✅ `StockSeeder` - Initial stock distributed across locations
    - ✅ `DatabaseSeeder` - 3 test users with assigned roles

### 🔐 Authentication & Authorization (Week 4) ✅ COMPLETED

- ✅ Implement user roles and permissions using [spatie/laravel-permission](https://github.com/spatie/laravel-permission) (e.g., `admin`, `stock_manager`, `operator`).
- ✅ Develop route protection using custom middleware:
    - ✅ Use Spatie's built-in middleware (`role`, `permission`, `role_or_permission`)
    - ✅ Create custom `CheckInventoryAccess` middleware for inventory-specific access control
    - ✅ Register middleware in Laravel 12 bootstrap configuration
    - ✅ Create protected route groups with appropriate middleware
    - ✅ Test permission system with different user roles (verified working)

### 🧩 Inertia API & Controllers Development (Weeks 5–7)

- **Products & Categories**: Full CRUD with Laravel controllers returning Inertia responses with props.
- **Stock & Locations**: Controllers for managing stock quantities and locations, passing data to React components via Inertia.
- **Stock Transactions**: Controllers to register inbound, outbound, and adjustment stock transactions.
- **Suppliers & Purchase Orders**: CRUD for suppliers and handling purchase orders with their details.
- **Users**: Controllers to manage users and allow role modifications (admin-level).
- Implement request validation and server-side error handling using Laravel.
- Implement pagination, filtering, and sorting via Laravel and pass results to React through Inertia.

### 🧪 Backend Testing (Ongoing, end of Week 7)

- Write **unit tests** for models and business logic.
- Write **integration tests** for controllers and route behavior.

---

## Phase 2: Frontend Development (React with Inertia.js) – Weeks 8 to 12

### 📦 State & Data Management (Week 9)

- Data is primarily passed from Laravel controllers to React components via Inertia props.
- Choose and integrate a state manager for local/global states if necessary:
    - Use **Context API** or **Zustand** for UI states outside Inertia flow.
- Develop Inertia-powered forms for create/update flows.

### 🖥️ UI Development (Weeks 10–12)

- **Authentication**: Login & registration pages (already did with Laravel 12 react starter kit).
- **Dashboard**: Display key stats (e.g., low stock, recent transactions) using data from Inertia props.
- **Products**: Product list with search, filters, create/edit via Inertia.
- **Stock**: Detailed views per product/location, alerts, interactive actions via Inertia.
- **Transactions**: Stock movement history, ability to create new transactions via forms.
- **Suppliers & Purchase Orders**: Manage suppliers and track orders through Inertia.
- **Users & Roles**: Admin interface to manage users and permissions via Inertia.
- Use **shadcn/ui** components and **lucide-react** icons for a modern, responsive UI.
- Implement client-side form validation when necessary (in addition to server-side Laravel validation).

---

## Phase 3: Integration & Testing – Weeks 13 to 14

### 🔄 Frontend–Backend Integration (Week 13)

- Ensure smooth data flow between Laravel (controllers/models) and React (components) via Inertia.js.
- Handle loading states, validation errors, and user notifications.

### ✅ End-to-End Testing (Week 14)

- Perform functional testing from frontend to backend.
- Test complex workflows, including user permission scenarios.
- Run performance and responsiveness tests on the user interface.
- Bug fixing and final polish.

---
