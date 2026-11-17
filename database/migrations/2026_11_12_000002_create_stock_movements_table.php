<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Historical migration name retained for databases created before the merge.
        // Creation is now owned by 2026_11_11_233934_create_stock_movements_table.
        // Existing schemas are reconciled by 2026_11_17_000001.
    }

    public function down(): void
    {
        // The earlier creation migration owns the table.
    }
};
