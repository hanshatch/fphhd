<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY COLUMN institution ENUM('banamex','mercadopago','nu','revolut','amex','other') DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE accounts MODIFY COLUMN institution ENUM('banamex','mercadopago','nu','revolut','other') DEFAULT 'other'");
    }
};
