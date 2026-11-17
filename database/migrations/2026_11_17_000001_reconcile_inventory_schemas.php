<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'deleted_at')) {
            Schema::table('products', fn (Blueprint $table) => $table->softDeletes());
        }

        // Both the old enum (entrada/saida) and the local varchar (entry/exit)
        // must accept the canonical values before any records are converted.
        Schema::table('stock_movements', fn (Blueprint $table) => $table->string('type', 7)->change());
        DB::table('stock_movements')->where('type', 'entrada')->update(['type' => 'entry']);
        DB::table('stock_movements')->where('type', 'saida')->update(['type' => 'exit']);

        if (! Schema::hasColumn('stock_movements', 'quantity_before')) {
            Schema::table('stock_movements', fn (Blueprint $table) => $table->unsignedInteger('quantity_before')->default(0));
            DB::table('stock_movements')->update([
                'quantity_before' => DB::raw("CASE WHEN type = 'entry' THEN quantity_after - quantity ELSE quantity_after + quantity END"),
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only compatibility migration: these columns and records may
        // predate this merge. Never drop them or reinterpret historical data.
    }
};
