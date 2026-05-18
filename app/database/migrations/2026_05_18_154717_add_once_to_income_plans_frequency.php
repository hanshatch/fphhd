<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        DB::statement("ALTER TABLE income_plans MODIFY COLUMN frequency ENUM('once','weekly','biweekly','monthly') DEFAULT 'biweekly'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        DB::statement("ALTER TABLE income_plans MODIFY COLUMN frequency ENUM('biweekly','monthly','weekly') DEFAULT 'biweekly'");
    }
};
