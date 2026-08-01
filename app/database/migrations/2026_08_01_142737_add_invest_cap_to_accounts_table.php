<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tope de saldo al que aplica el APR (Klar, Nu y demás pagan la tasa
     * alta solo hasta cierto monto; el excedente rinde otra cosa o nada).
     * NULL = sin tope, la tasa aplica a todo el saldo.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('invest_cap', 14, 2)->nullable()->after('invest_apr')
                ->comment('Saldo máximo al que aplica invest_apr');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('invest_cap');
        });
    }
};
