<?php
/**
 * YNSUMEDIC — SMTP / mail configuration (EXAMPLE)
 * ------------------------------------------------
 * Copy this file to `config.smtp.php` ON THE SERVER ONLY and fill in the
 * real credentials. `config.smtp.php` is git-ignored and never deployed.
 *
 * transport:
 *   - 'smtp' : authenticate against the cPanel mailbox (recommended)
 *   - 'mail' : PHP mail() (only if the hosting provider allows it)
 *   - 'log'  : do not send; store submissions in api/logs (local testing)
 */

return [
    // Destination mailbox for leads / reclamaciones
    'to'      => 'comercial@ynsumedic.com',
    'to_name' => 'YNSUMEDIC',

    // Envelope sender. Must belong to the domain (SPF/DKIM friendliness).
    'from'      => 'web@ynsumedic.com',
    'from_name' => 'Web YNSUMEDIC',

    // Libro de Reclamaciones: destination and sender (mailbox must exist)
    'claims_to'   => 'reclamaciones@ynsumedic.com',
    'claims_from' => 'reclamaciones@ynsumedic.com',

    // 'smtp' | 'mail' | 'log'
    'transport' => 'smtp',

    'smtp' => [
        'host'    => 'mail.ynsumedic.com',
        'port'    => 465,
        'secure'  => 'ssl',   // 'ssl' (465) | 'tls' (587) | '' (25, not recommended)
        'user'    => 'web@ynsumedic.com',
        'pass'    => 'CHANGE_ME',
        'timeout' => 15,
    ],

    // Send a courtesy copy to the person who submitted the form
    'autoreply' => true,

    /* ── Anti-abuse limits ──────────────────────────────────── */
    'rate_limit' => [
        // Fallback for any endpoint without a specific rule
        'max'    => 5,
        'window' => 600,

        // Maximum submissions per IP across every endpoint
        'global' => ['max' => 15, 'window' => 3600],

        // Maximum submissions per IP per day
        'daily'  => ['max' => 30, 'window' => 86400],

        // Per endpoint rules (max submissions per IP inside the window)
        'buckets' => [
            'contact' => [
                'max'     => 5,
                'window'  => 600,
                'message' => 'Ha enviado demasiadas solicitudes de cotización desde esta conexión. Espere unos minutos o escríbanos por WhatsApp.',
            ],
            'newsletter' => [
                'max'     => 3,
                'window'  => 3600,
                'message' => 'Ha enviado demasiadas suscripciones desde esta conexión. Espere un momento o escríbanos por WhatsApp.',
            ],
            'reclamaciones' => [
                'max'     => 1,
                'window'  => 1800,
                'message' => 'Solo se puede registrar una hoja de reclamaciones cada 30 minutos desde la misma conexión. Si necesita registrar otro caso, escríbanos por WhatsApp o llámenos.',
            ],
            // Opcional (Libro de Reclamaciones): tope diario por IP y topes de todo el sitio
            'reclamaciones_ip_day'    => ['max' => 5,  'window' => 86400],
            'reclamaciones_site_hour' => ['max' => 20, 'window' => 3600],
            'reclamaciones_site_day'  => ['max' => 80, 'window' => 86400],
        ],
    ],

    'anti_spam' => [
        // Minimum seconds between rendering and submitting a form (bot filter)
        'min_seconds'      => 3,
        // Opcional: vigencia (en segundos) del token firmado del Libro de Reclamaciones
        'claims_min_seconds' => 5,
        'claims_max_seconds' => 7200,
        // Window used to detect repeated submissions with the same data
        'duplicate_window' => 86400,
        // Courtesy copies sent to the same mailbox per day
        'email_quota'      => ['max' => 5, 'window' => 86400],
    ],

    // Optional: force a From address different from the SMTP user
    'log_dir' => __DIR__ . '/logs',

    // hCaptcha for the Libro de Reclamaciones (https://dashboard.hcaptcha.com).
    // Register ynsumedic.com in the dashboard. Empty keys = submissions rejected.
    'captcha' => [
        'site_key'        => 'TU_SITE_KEY_AQUI',
        'secret'          => 'TU_SECRET_KEY_AQUI',
        'allow_localhost' => false,   // true only for local development
    ],
];
