<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correos de notificación bancaria leídos de Gmail, con lo interpretado y
 * el estado de cada uno. Además, terminación de cuenta/tarjeta en accounts
 * para empatar "Cheques ***379" con la cuenta correcta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_emails', function (Blueprint $table) {
            $table->id();
            $table->string('message_uid', 64)->unique();
            $table->string('bank', 30)->nullable();
            $table->string('sender', 200);
            $table->string('subject', 300);
            $table->timestamp('received_at');
            $table->text('raw_text')->nullable();
            $table->json('parsed')->nullable();
            $table->enum('status', ['pending', 'registered', 'duplicate', 'skipped', 'unparsed', 'ignored'])->default('pending');
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auth_number', 40)->nullable()->index();
            $table->timestamps();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('bank_last4', 8)->nullable()->after('institution');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('bank_last4');
        });

        Schema::dropIfExists('bank_emails');
    }
};
