<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->unsignedTinyInteger('statement_day')->comment('Día del mes en que cierra el periodo (1-31)');
            $table->unsignedTinyInteger('payment_day')->comment('Día límite de pago sin intereses (1-31)');
            $table->decimal('credit_limit', 14, 2);
            $table->decimal('apr', 5, 2)->nullable()->comment('Tasa anual de interés');
            $table->decimal('min_payment_pct', 5, 2)->default(1.5)->comment('% mínimo de pago sobre saldo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
