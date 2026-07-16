<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        DB::statement("ALTER TABLE accounts MODIFY COLUMN institution ENUM('banamex','klar','mercadopago','nu','revolut','amex','efectivo','other') DEFAULT 'other'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        DB::statement("ALTER TABLE accounts MODIFY COLUMN institution ENUM('banamex','mercadopago','nu','revolut','amex','efectivo','other') DEFAULT 'other'");
    }
};
