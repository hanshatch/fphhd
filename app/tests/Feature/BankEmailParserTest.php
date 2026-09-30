<?php

namespace Tests\Feature;

use App\Services\Mail\BankEmailParser;
use App\Services\Mail\GmailImapReader;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BankEmailParserTest extends TestCase
{
    public const BANAMEX_RETIRO = <<<TXT
Banamex
Sep 30, 2026 02:56:55 PM
Se realizó la siguiente operación: Retiro/Compra
HANS HATCH
Cheques M.N. ***379
Detalle de la operación
Monto $ 20,000.00 M.N.
Fecha y hora 30 Septiembre 2026 / 14:56:00
Estatus Exitoso
No. Autorización 383450
TXT;

    public const BANAMEX_DEPOSITO = <<<TXT
Banamex
sep 29, 2026 02:03:00 AM
Se realizó la siguiente operación: Depósito a cuenta o tarjeta
HANS HATCH
Cheques ***379
Detalle de la operación
Monto $ 50,411.94 M.N.
Fecha y hora 29 septiembre 2026 / 02:03:00
No. Autorización 45650
Información importante de tu tarjeta
TXT;

    public const REVOLUT_ENVIO = <<<TXT
Revolut
Tu transferencia fue exitosa
Hola Hans,
Enviaste 5.800 BRL a Samanta Motta Hatch. Puedes encontrar los datos a continuación:
Para
Samanta Motta Hatch
Enviado
5.800 BRL
Referencia
Enviada desde Revolut
El
miércoles, 30 septiembre, 15:08
Llegada esperada
Mañana
TXT;

    public const BANAMEX_TARJETA = <<<TXT
26 sep 2026, 11:44 h.
Se realizó la siguiente operación: Retiro / Compra
HANS HATCH
CONQUISTA BANAMEX**117
Detalle de la operación
Monto
$377.00
Establecimiento
ECOMMERCE SAN PABLO MEX
Fecha y hora
2026/09/26 11:44:43 AM
Estatus
Exitoso
No. Autorización
300227
Información importante de tu tarjeta
Fecha de corte
18 septiembre 2026
Fecha límite de pago
9 octubre 2026
Mínimo a pagar*
$12,080.00
TXT;

    private function parser(): BankEmailParser
    {
        return app(BankEmailParser::class);
    }

    public function test_detects_bank_by_sender(): void
    {
        $p = $this->parser();

        $this->assertSame('revolut', $p->bankFor('no-reply@revolut.com'));
        $this->assertSame('banamex', $p->bankFor('notificaciones@banamex.com'));
        $this->assertSame('banamex', $p->bankFor('avisos@mail.citibanamex.com'));
        $this->assertNull($p->bankFor('newsletter@tienda.com'));
    }

    public function test_banamex_retiro(): void
    {
        $r = $this->parser()->parse('notificaciones@banamex.com', 'Retiro/Compra con cuenta Banamex', self::BANAMEX_RETIRO, Carbon::parse('2026-09-30 14:56'));

        $this->assertSame('banamex', $r['bank']);
        $this->assertSame('expense', $r['kind']);
        $this->assertSame('20000.00', $r['amount']);
        $this->assertSame('MXN', $r['currency']);
        $this->assertSame('2026-09-30', $r['date']);
        $this->assertSame('379', $r['last4']);
        $this->assertSame('383450', $r['auth']);
        $this->assertSame('Retiro/Compra Banamex', $r['description']);
        $this->assertTrue($r['generic']);
    }

    public function test_banamex_deposito(): void
    {
        $r = $this->parser()->parse('notificaciones@banamex.com', 'Depósito a cuenta Banamex', self::BANAMEX_DEPOSITO, Carbon::parse('2026-09-29 02:03'));

        $this->assertSame('income', $r['kind']);
        $this->assertSame('50411.94', $r['amount']);
        $this->assertSame('2026-09-29', $r['date']);
        $this->assertSame('379', $r['last4']);
        $this->assertSame('45650', $r['auth']);
    }

    public function test_banamex_card_purchase_has_merchant_and_card_digits(): void
    {
        $r = $this->parser()->parse('notificaciones@banamex.com', 'Retiro/Compra con tarjeta Banamex', self::BANAMEX_TARJETA, Carbon::parse('2026-09-26 11:45'));

        $this->assertSame('expense', $r['kind']);
        $this->assertSame('377.00', $r['amount']);
        $this->assertSame('2026-09-26', $r['date']);
        $this->assertSame('117', $r['last4']);
        $this->assertSame('300227', $r['auth']);
        $this->assertSame('Ecommerce San Pablo Mex', $r['description']);
        $this->assertFalse($r['generic']);
    }

    public function test_revolut_transfer_abroad(): void
    {
        $r = $this->parser()->parse('no-reply@revolut.com', 'Enviaste 5.800 BRL a Samanta Motta Hatch 💸', self::REVOLUT_ENVIO, Carbon::parse('2026-09-30 15:09'));

        $this->assertSame('revolut', $r['bank']);
        $this->assertSame('transfer_out', $r['kind']);
        $this->assertSame('5800.00', $r['amount']);
        $this->assertSame('BRL', $r['currency']);
        $this->assertSame('2026-09-30', $r['date']);
        $this->assertSame('Transferencia a Samanta Motta Hatch', $r['description']);
    }

    public function test_revolut_card_payment_in_pesos(): void
    {
        $r = $this->parser()->parse('no-reply@revolut.com', 'Pagaste 64,50 MXN en OXXO', "Hola Hans,\nEl martes, 8 septiembre, 10:08", Carbon::parse('2026-09-08 10:09'));

        $this->assertSame('expense', $r['kind']);
        $this->assertSame('64.50', $r['amount']);
        $this->assertSame('MXN', $r['currency']);
        $this->assertSame('OXXO', $r['description']);
        $this->assertFalse($r['generic']);
    }

    public function test_unknown_subject_returns_null(): void
    {
        $this->assertNull($this->parser()->parse('notificaciones@banamex.com', 'Tu estado de cuenta ya está disponible', 'Hola', now()));
    }

    public function test_html_to_text_keeps_rows_readable(): void
    {
        $html = '<table><tr><td>Monto</td><td>$ 20,000.00 M.N.</td></tr><tr><td>No. Autorización</td><td>383450</td></tr></table>';
        $text = GmailImapReader::htmlToText($html);

        $this->assertStringContainsString("Monto $ 20,000.00 M.N.", $text);
        $this->assertStringContainsString("No. Autorización 383450", $text);
    }
}
