<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una regla de ingreso también recuerda la fuente: «Abono/Nomina»
     * siempre es pago del Tec, aunque el banco no lo diga.
     */
    public function up(): void
    {
        Schema::table('merchant_rules', function (Blueprint $table) {
            $table->foreignId('source_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('merchant_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_id');
        });
    }
};
