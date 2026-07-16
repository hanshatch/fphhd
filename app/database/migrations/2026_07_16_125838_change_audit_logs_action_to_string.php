<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El enum de action requería un ALTER por cada acción nueva (profile_update
     * no cabía). String + constantes en el modelo es suficiente y flexible.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('action', 50)->change();
        });
    }

    public function down(): void
    {
        // Sin retorno a enum: los valores nuevos no cabrían en la definición original.
    }
};
