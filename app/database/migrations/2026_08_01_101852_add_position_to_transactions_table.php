<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orden manual dentro de un mismo día, para poder acomodar los
     * movimientos igual que aparecen en el estado de cuenta del banco.
     * 0 = sin acomodar; entonces desempata el id, como hasta ahora.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('date');
            $table->index(['account_id', 'date', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'date', 'position']);
            $table->dropColumn('position');
        });
    }
};
