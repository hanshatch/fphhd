<?php

namespace App\Services\Mail;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Lee la etiqueta de Gmail (carpeta IMAP) con la extensión imap de PHP y
 * una contraseña de aplicación. Solo lectura: nunca marca ni borra correos.
 */
class GmailImapReader implements MailboxReader
{
    /**
     * novalidate-cert: la librería c-client de PHP no manda SNI y Gmail le
     * responde con un certificado de relleno; la conexión sigue cifrada.
     */
    private const HOST = '{imap.gmail.com:993/imap/ssl/novalidate-cert}';

    public function isConfigured(): bool
    {
        return function_exists('imap_open')
            && (bool) config('services.gmail.user')
            && (bool) config('services.gmail.password');
    }

    public function fetchRecent(int $days = 7): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $label   = (string) config('services.gmail.label', 'FP');
        $mailbox = self::HOST . imap_utf7_encode($label);
        $stream  = @imap_open($mailbox, config('services.gmail.user'), str_replace(' ', '', (string) config('services.gmail.password')), OP_READONLY, 1);

        if ($stream === false) {
            Log::warning('Gmail IMAP: no se pudo abrir la etiqueta', ['label' => $label, 'error' => imap_last_error()]);
            imap_errors(); // vaciar la pila para que PHP no la vuelque al cerrar

            return [];
        }

        try {
            $since = now()->subDays($days)->format('j-M-Y');
            $uids  = imap_search($stream, 'SINCE "' . $since . '"', SE_UID) ?: [];
            rsort($uids);

            $messages = [];

            foreach (array_slice($uids, 0, 100) as $uid) {
                $header = imap_headerinfo($stream, imap_msgno($stream, $uid));

                if ($header === false) {
                    continue;
                }

                $from = isset($header->from[0])
                    ? mb_strtolower($header->from[0]->mailbox . '@' . $header->from[0]->host)
                    : '';

                $messages[] = [
                    'uid'     => (string) $uid,
                    'from'    => $from,
                    'subject' => $this->decodeHeader($header->subject ?? ''),
                    'date'    => isset($header->udate) ? Carbon::createFromTimestamp($header->udate) : now(),
                    'text'    => $this->bodyText($stream, $uid),
                ];
            }

            return $messages;
        } finally {
            imap_errors();
            imap_close($stream);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function decodeHeader(string $value): string
    {
        $parts = imap_mime_header_decode($value) ?: [];
        $out   = '';

        foreach ($parts as $part) {
            $charset = strtolower($part->charset) === 'default' ? 'UTF-8' : $part->charset;
            $out    .= @mb_convert_encoding($part->text, 'UTF-8', $charset) ?: $part->text;
        }

        return trim($out);
    }

    /** Texto plano del correo: prefiere text/plain; si no, HTML convertido a texto */
    private function bodyText($stream, int $uid): string
    {
        $structure = imap_fetchstructure($stream, $uid, FT_UID);
        $plain     = null;
        $html      = null;

        $walk = function ($part, string $section) use (&$walk, &$plain, &$html, $stream, $uid) {
            if (isset($part->parts) && is_array($part->parts)) {
                foreach ($part->parts as $i => $sub) {
                    $walk($sub, $section === '' ? (string) ($i + 1) : $section . '.' . ($i + 1));
                }

                return;
            }

            if (($part->type ?? null) !== TYPETEXT) {
                return;
            }

            $body = imap_fetchbody($stream, $uid, $section === '' ? '1' : $section, FT_UID | FT_PEEK);
            $body = match ($part->encoding ?? 0) {
                ENCBASE64          => base64_decode($body),
                ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
                default            => $body,
            };

            $charset = 'UTF-8';
            foreach ($part->parameters ?? [] as $param) {
                if (strtolower($param->attribute) === 'charset') {
                    $charset = $param->value;
                }
            }
            $body = @mb_convert_encoding($body, 'UTF-8', $charset) ?: $body;

            $subtype = strtolower($part->subtype ?? '');
            if ($subtype === 'plain' && $plain === null) {
                $plain = $body;
            } elseif ($subtype === 'html' && $html === null) {
                $html = $body;
            }
        };

        if (isset($structure->parts)) {
            $walk($structure, '');
        } else {
            // Mensaje de una sola parte
            $body = imap_body($stream, $uid, FT_UID | FT_PEEK);
            $body = match ($structure->encoding ?? 0) {
                ENCBASE64          => base64_decode($body),
                ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
                default            => $body,
            };
            if (strtolower($structure->subtype ?? '') === 'html') {
                $html = $body;
            } else {
                $plain = $body;
            }
        }

        return $plain !== null && trim($plain) !== ''
            ? self::normalizeText($plain)
            : self::htmlToText((string) $html);
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html);
        $html = preg_replace('/<br\s*\/?>|<\/(p|div|tr|li|h[1-6]|table)>/i', "\n", $html);
        $html = preg_replace('/<\/t[dh]>/i', ' ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::normalizeText($text);
    }

    public static function normalizeText(string $text): string
    {
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\s*\n\s*/', "\n", $text);

        return trim($text);
    }
}
