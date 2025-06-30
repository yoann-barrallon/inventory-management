<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Tax Rate
    |--------------------------------------------------------------------------
    |
    | The default tax rate to apply to purchase orders when no rate is specified.
    | This value is in percentage (e.g., 18.5 for 18.5%).
    |
    */

    'default_tax_rate' => env('INVENTORY_DEFAULT_TAX_RATE', 10.0),

    /*
    |--------------------------------------------------------------------------
    | Default Location
    |--------------------------------------------------------------------------
    |
    | The default location ID to use for stock operations when no location
    | is specified. Set to null to require location selection.
    |
    */

    'default_location_id' => env('INVENTORY_DEFAULT_LOCATION_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | The default threshold below which products are considered low stock.
    | Individual products can override this value.
    |
    */

    'low_stock_threshold' => env('INVENTORY_LOW_STOCK_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Purchase Order Settings
    |--------------------------------------------------------------------------
    */

    'purchase_orders' => [
        
        /*
        |--------------------------------------------------------------------------
        | Order Number Prefix
        |--------------------------------------------------------------------------
        |
        | Prefix for auto-generated purchase order numbers.
        |
        */

        'order_number_prefix' => env('INVENTORY_PO_PREFIX', 'PO'),

        /*
        |--------------------------------------------------------------------------
        | Auto-confirm Orders
        |--------------------------------------------------------------------------
        |
        | Automatically confirm purchase orders upon creation.
        |
        */

        'auto_confirm' => env('INVENTORY_AUTO_CONFIRM_PO', false),

        /*
        |--------------------------------------------------------------------------
        | Require Expected Date
        |--------------------------------------------------------------------------
        |
        | Require an expected delivery date for all purchase orders.
        |
        */

        'require_expected_date' => env('INVENTORY_REQUIRE_EXPECTED_DATE', true),

        /*
        |--------------------------------------------------------------------------
        | Allow Partial Receiving
        |--------------------------------------------------------------------------
        |
        | Allow receiving partial quantities of purchase order items.
        |
        */

        'allow_partial_receiving' => env('INVENTORY_ALLOW_PARTIAL_RECEIVING', true),

        /*
        |--------------------------------------------------------------------------
        | Allow Over-receiving
        |--------------------------------------------------------------------------
        |
        | Allow receiving more than the ordered quantity.
        |
        */

        'allow_over_receiving' => env('INVENTORY_ALLOW_OVER_RECEIVING', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Stock Transaction Settings
    |--------------------------------------------------------------------------
    */

    'stock_transactions' => [

        /*
        |--------------------------------------------------------------------------
        | Require Reason
        |--------------------------------------------------------------------------
        |
        | Require a reason for all stock transactions.
        |
        */

        'require_reason' => env('INVENTORY_REQUIRE_TRANSACTION_REASON', true),

        /*
        |--------------------------------------------------------------------------
        | Auto-approve Adjustments
        |--------------------------------------------------------------------------
        |
        | Automatically approve stock adjustments below this threshold.
        |
        */

        'auto_approve_threshold' => env('INVENTORY_AUTO_APPROVE_THRESHOLD', 100),

    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */

    'notifications' => [

        /*
        |--------------------------------------------------------------------------
        | Low Stock Notifications
        |--------------------------------------------------------------------------
        |
        | Send notifications when products reach low stock levels.
        |
        */

        'low_stock_enabled' => env('INVENTORY_NOTIFY_LOW_STOCK', true),

        /*
        |--------------------------------------------------------------------------
        | Purchase Order Notifications
        |--------------------------------------------------------------------------
        |
        | Send notifications for purchase order events.
        |
        */

        'purchase_order_events' => [
            'created' => env('INVENTORY_NOTIFY_PO_CREATED', true),
            'confirmed' => env('INVENTORY_NOTIFY_PO_CONFIRMED', true),
            'received' => env('INVENTORY_NOTIFY_PO_RECEIVED', true),
            'overdue' => env('INVENTORY_NOTIFY_PO_OVERDUE', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Notification Recipients
        |--------------------------------------------------------------------------
        |
        | Email addresses to receive inventory notifications.
        |
        */

        'recipients' => [
            'stock_manager' => env('INVENTORY_STOCK_MANAGER_EMAIL'),
            'purchasing' => env('INVENTORY_PURCHASING_EMAIL'),
            'admin' => env('INVENTORY_ADMIN_EMAIL'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Settings
    |--------------------------------------------------------------------------
    */

    'reporting' => [

        /*
        |--------------------------------------------------------------------------
        | Default Date Range
        |--------------------------------------------------------------------------
        |
        | Default number of days to include in reports.
        |
        */

        'default_date_range_days' => env('INVENTORY_DEFAULT_REPORT_DAYS', 30),

        /*
        |--------------------------------------------------------------------------
        | Cache Reports
        |--------------------------------------------------------------------------
        |
        | Cache report data for improved performance.
        |
        */

        'cache_enabled' => env('INVENTORY_CACHE_REPORTS', true),

        /*
        |--------------------------------------------------------------------------
        | Cache Duration
        |--------------------------------------------------------------------------
        |
        | How long to cache report data (in minutes).
        |
        */

        'cache_duration' => env('INVENTORY_CACHE_DURATION', 60),

    ],

]; 