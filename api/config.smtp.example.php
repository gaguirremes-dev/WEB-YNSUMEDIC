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

    // Anti-spam: max submissions per IP inside the window (seconds)
    'rate_limit' => [
        'max'    => 5,
        'window' => 600,
    ],

    // Optional: force a From address different from the SMTP user
    'log_dir' => __DIR__ . '/logs',
];
