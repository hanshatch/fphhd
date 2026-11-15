<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transferencia entre cuentas propias de la que aún no se sabe el otro
     * lado: 'out' = salió de account_id, 'in' = entró a account_id. Con
     * counterparty_account_id vacío hasta que se concilia; entonces vuelve a null.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('transfer_pending', 3)->nullable()->after('counterparty_account_id');
            $table->index(['transfer_pending', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['transfer_pending', 'amount']);
            $table->dropColumn('transfer_pending');
        });
    }
};
