<?php

namespace App\Services\Mail;

/**
 * Fuente de correos bancarios. La implementación real lee Gmail por IMAP;
 * en pruebas se sustituye por una lista en memoria.
 */
interface MailboxReader
{
    public function isConfigured(): bool;

    /**
     * Correos recientes de la etiqueta configurada, más nuevos primero.
     * Cada uno: ['uid' => string, 'from' => string, 'subject' => string,
     *            'date' => Carbon, 'text' => string]
     *
     * @return array<int, array>
     */
    public function fetchRecent(int $days = 7): array;
}
