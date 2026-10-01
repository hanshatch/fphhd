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

    public const BANAMEX_DOMICILIADO = <<<TXT
Operación
Cargo a cuenta para pago a Establecimiento
Establecimiento
SEGUROS MONTERREY NE
Cuenta de cargo
894
Referencia establecimiento
CAD202692920208
Referencia 4
PAGO DE SERVICIO SEGUROSMON
Monto
$11,040.20
Fecha y hora
29/09/26 19:26:18 PM
No. Autorización
S702 0004419739
Protege tus cuentas
Nunca compartas con nadie
TXT;

    public const NU_RECIBIDA = <<<TXT
Transferencia recibida
Hola, Hans:
HANS,HATCH/DORANTES hizo una transferencia a tu Cuenta Nu por:Monto: $4,000.00
Fecha: 30 SEP 2026
Hora: 23:04
Esta cantidad ya está disponible en tu cuenta.
TXT;

    public const NU_ENVIADA = <<<TXT
Transferencia exitosa
Hola, Hans:
Nos da gusto confirmar que la transferencia que hiciste a la cuenta de Hans Revolut en STP fue exitosa.
Monto: $150.00
Fecha: 07/02/2026
Hora: 13:10
TXT;

    public const NU_RECARGA = <<<TXT
Aquí está tu comprobante de tu pago de recarga de celular
24 sep 2026 - 06:19:48
Detalle
Monto: $200.00
Costo extra: $0
Tipo de transacción: Recarga telefónica
Número de celular: 5530808688
Recibe
Empresa a la cual se realizará el pago: Telcel Amigo Sin Límite
Envía
Nombre: Hans Hatch Dorantes
Método de pago: Cuenta Nu
Información adicional
Código de operación: b4116c00-31e6-4911-9538-9ee34b7040cc
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

    public function test_banamex_direct_debit(): void
    {
        $r = $this->parser()->parse('notificaciones@banamex.com', 'Cargo a cuenta para pago a Establecimiento', self::BANAMEX_DOMICILIADO, Carbon::parse('2026-09-29 19:27'));

        $this->assertSame('expense', $r['kind']);
        $this->assertSame('11040.20', $r['amount']);
        $this->assertSame('2026-09-29', $r['date']);
        $this->assertSame('894', $r['last4']);
        $this->assertSame('S702 0004419739', $r['auth']);
        $this->assertSame('Seguros Monterrey Ne', $r['description']);
        $this->assertFalse($r['generic']);
    }

    public function test_banamex_direct_debit_registration_notice_is_info(): void
    {
        $r = $this->parser()->parse('notificaciones@banamex.com', 'Autorización de cargo a cuenta para pago a establecimiento', "Se registró una nueva domiciliación\nCuenta de cargo: 894", now());

        $this->assertSame('info', $r['kind']);
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

    public function test_nu_received_transfer(): void
    {
        $r = $this->parser()->parse('nu@nu.com.mx', '¡Recibiste una transferencia!', self::NU_RECIBIDA, Carbon::parse('2026-10-01 05:04'));

        $this->assertSame('nu', $r['bank']);
        $this->assertSame('income', $r['kind']);
        $this->assertSame('4000.00', $r['amount']);
        $this->assertSame('2026-09-30', $r['date']);
        $this->assertSame('HANS,HATCH/DORANTES', $r['counterparty']);
        $this->assertSame('Transferencia de Hans Hatch Dorantes', $r['description']);
    }

    public function test_nu_sent_transfer_with_numeric_date(): void
    {
        $r = $this->parser()->parse('nu@nu.com.mx', 'Tu transferencia fue exitosa', self::NU_ENVIADA, Carbon::parse('2026-02-07 13:11'));

        $this->assertSame('transfer_out', $r['kind']);
        $this->assertSame('150.00', $r['amount']);
        $this->assertSame('2026-02-07', $r['date']);
        $this->assertSame('Hans Revolut STP', $r['counterparty']);
        $this->assertSame('Transferencia a Hans Revolut (STP)', $r['description']);

        $oct = $this->parser()->parse('nu@nu.com.mx', 'Tu transferencia fue exitosa',
            "la transferencia que hiciste a la cuenta de Miranda S en NU MEXICO fue exitosa.\nMonto: $850.00\nFecha: 09/OCT/2025", now());
        $this->assertSame('2025-10-09', $oct['date']);
        $this->assertSame('850.00', $oct['amount']);
    }

    public function test_nu_phone_top_up(): void
    {
        $r = $this->parser()->parse('nu@nu.com.mx', 'Tu comprobante de pago de recarga de celular', self::NU_RECARGA, Carbon::parse('2026-09-24 06:20'));

        $this->assertSame('expense', $r['kind']);
        $this->assertSame('200.00', $r['amount']);
        $this->assertSame('2026-09-24', $r['date']);
        $this->assertSame('Telcel Amigo Sin Límite · 5530808688', $r['description']);
        $this->assertSame('b4116c00-31e6-4911-9538-9ee34b7040cc', $r['auth']);
        $this->assertFalse($r['generic']);
    }

    public function test_nu_notice_without_amount_is_info(): void
    {
        $r = $this->parser()->parse('nu@nu.com.mx', 'Agregaste un contacto a tu cuenta', 'Hola Hans, agregaste a Miranda como contacto.', now());

        $this->assertSame('info', $r['kind']);
    }

    public const OPENBANK_ABONO = "Abonaste dinero a tus Apartados Open\nAbonaste $ 24,400.00 desde tu cuenta ****9617 a tus Apartados Open el 24/07/2026 a las 07:43:48.\n¿No reconoces esta operación? Llama a la Línea Open: 55 7005 5755.";

    public function test_openbank_top_up_and_move_to_apartados(): void
    {
        $r = $this->parser()->parse('noreply@openbank.mx', 'Recarga exitosa ✅',
            "Tu recarga está lista\n¡Hola, Hans Hatch Dorantes!\nTu recarga de tiempo aire al teléfono 5543589391 por $200.00 se confirmó correctamente.", Carbon::parse('2026-10-01 06:43'));

        $this->assertSame('openbank', $r['bank']);
        $this->assertSame('expense', $r['kind']);
        $this->assertSame('200.00', $r['amount']);
        $this->assertSame('2026-10-01', $r['date']);
        $this->assertSame('Recarga tiempo aire · 5543589391', $r['description']);

        $a = $this->parser()->parse('noreply@openbank.mx', 'Abono exitoso ✅', self::OPENBANK_ABONO, Carbon::parse('2026-07-24 07:44'));

        $this->assertSame('to_savings', $a['kind']);
        $this->assertSame('24400.00', $a['amount']);
        $this->assertSame('2026-07-24', $a['date']);
        $this->assertSame('9617', $a['last4']);

        $info = $this->parser()->parse('noreply@openbank.mx', 'Aquí está tu código 🔐', 'Tu código es 123456. No lo compartas.', now());
        $this->assertSame('info', $info['kind']);
    }

    public function test_html_sent_as_plain_text_is_converted(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Recarga exitosa</title><style>p{color:red}</style></head>'
            . '<body><table><tr><td>Tu recarga de tiempo aire al tel&eacute;fono 5512420504 por $10.00 se confirm&oacute;.</td></tr></table></body></html>';

        $text = GmailImapReader::htmlToText($html);

        $this->assertStringNotContainsString('Recarga exitosa', $text); // el <title> del <head> no se cuela
        $this->assertStringNotContainsString('color:red', $text);
        $this->assertStringContainsString('Tu recarga de tiempo aire al teléfono 5512420504 por $10.00', $text);
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
