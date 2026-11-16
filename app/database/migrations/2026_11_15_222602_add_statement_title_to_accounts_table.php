<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Título de la pantalla del banco con la que se importa esta cuenta (ej. "Apartado Open") */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('statement_title', 120)->nullable()->after('bank_last4');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('statement_title');
        });
    }
};
