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
        // For SQLite compatibility, we need to recreate the table
        Schema::table('purchase_orders', function (Blueprint $table) {
            // SQLite doesn't support modifying enum columns, so we need to be creative
            // We'll add a temporary column, copy data, drop old column, rename new column
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

        // Now update the column to be an enum-like check constraint (SQLite compatible)
        DB::statement('CREATE TRIGGER check_purchase_order_status 
                      BEFORE UPDATE OF status ON purchase_orders
                      FOR EACH ROW WHEN NEW.status NOT IN (\'pending\', \'confirmed\', \'received\', \'partially_received\', \'cancelled\')
                      BEGIN
                          SELECT RAISE(ABORT, \'Invalid status value\');
                      END');

        DB::statement('CREATE TRIGGER check_purchase_order_status_insert 
                      BEFORE INSERT ON purchase_orders
                      FOR EACH ROW WHEN NEW.status NOT IN (\'pending\', \'confirmed\', \'received\', \'partially_received\', \'cancelled\')
                      BEGIN
                          SELECT RAISE(ABORT, \'Invalid status value\');
                      END');
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
        DB::statement('DROP TRIGGER IF EXISTS check_purchase_order_status');
        DB::statement('DROP TRIGGER IF EXISTS check_purchase_order_status_insert');
    }
};
