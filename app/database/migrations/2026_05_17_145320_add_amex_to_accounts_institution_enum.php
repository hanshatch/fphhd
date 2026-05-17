<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MODIFY COLUMN solo funciona en MySQL — se omite en SQLite (tests)
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE accounts MODIFY COLUMN institution ENUM('banamex','mercadopago','nu','revolut','amex','other') DEFAULT 'other'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE accounts MODIFY COLUMN institution ENUM('banamex','mercadopago','nu','revolut','other') DEFAULT 'other'");
    }
};
