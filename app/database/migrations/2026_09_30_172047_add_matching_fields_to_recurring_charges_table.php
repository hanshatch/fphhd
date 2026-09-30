<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Para empatar un cargo recurrente con lo que llega del banco (captura o
 * correo): texto con el que aparece en el estado de cuenta y tolerancia de
 * monto (cargos en USD/UDIs cambian unos pesos cada mes). 3% por defecto;
 * 0 = el monto debe coincidir exacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_charges', function (Blueprint $table) {
            $table->string('statement_text', 150)->nullable()->after('description');
            $table->decimal('amount_tolerance_pct', 5, 2)->default(3.00)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_charges', function (Blueprint $table) {
            $table->dropColumn(['statement_text', 'amount_tolerance_pct']);
        });
    }
};
