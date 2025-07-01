<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add the new status value to the existing enum
        Schema::table('purchase_orders', function (Blueprint $table) {
            // For PostgreSQL, we can use a check constraint instead of modifying enum
            $table->string('status_new')->default('pending');
        });

        // Copy existing data to new column
        DB::table('purchase_orders')->update(['status_new' => DB::raw('status')]);

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('status_new', 'status');
        });

        // Create trigger function for PostgreSQL
        DB::statement('
            CREATE OR REPLACE FUNCTION check_purchase_order_status_func()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.status NOT IN (\'pending\', \'confirmed\', \'received\', \'partially_received\', \'cancelled\') THEN
                    RAISE EXCEPTION \'Invalid status value: %\', NEW.status;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create trigger for UPDATE
        DB::statement('
            CREATE TRIGGER check_purchase_order_status
            BEFORE UPDATE OF status ON purchase_orders
            FOR EACH ROW
            EXECUTE FUNCTION check_purchase_order_status_func();
        ');

        // Create trigger for INSERT
        DB::statement('
            CREATE TRIGGER check_purchase_order_status_insert
            BEFORE INSERT ON purchase_orders
            FOR EACH ROW
            EXECUTE FUNCTION check_purchase_order_status_func();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, update any partially_received status to received
        DB::table('purchase_orders')
            ->where('status', 'partially_received')
            ->update(['status' => 'received']);

        // Drop the triggers
        DB::statement('DROP TRIGGER IF EXISTS check_purchase_order_status ON purchase_orders');
        DB::statement('DROP TRIGGER IF EXISTS check_purchase_order_status_insert ON purchase_orders');
        
        // Drop the trigger function
        DB::statement('DROP FUNCTION IF EXISTS check_purchase_order_status_func()');
    }
};
