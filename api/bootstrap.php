<?php
/**
 * YNSUMEDIC — shared helpers for the public API endpoints.
 * No framework, no dependencies. PHP 8.0+.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/Mailer.php';
require_once __DIR__ . '/lib/Captcha.php';

/* ── Configuration ─────────────────────────────────────────── */

function yns_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'to'         => 'comercial@ynsumedic.com',
        'to_name'    => 'YNSUMEDIC',
        'from'       => 'web@ynsumedic.com',
        'from_name'  => 'Web YNSUMEDIC',
        // Libro de Reclamaciones mailbox (destination and sender for claims)
        'claims_to'  => 'reclamaciones@ynsumedic.com',
        'claims_from' => 'reclamaciones@ynsumedic.com',
        'transport'  => 'mail',
        'smtp'       => [],
        'autoreply'  => true,
        'rate_limit' => [
            // Fallback limits for any endpoint without a specific bucket
            'max'    => 5,
            'window' => 600,
            // Maximum submissions per IP across every endpoint
            'global' => ['max' => 15, 'window' => 3600],
            // Maximum submissions per IP per day
            'daily'  => ['max' => 30, 'window' => 86400],
            // Per endpoint limits
            'buckets' => [
                'contact'       => ['max' => 5,  'window' => 600],
                'newsletter'    => ['max' => 3,  'window' => 3600],
                'reclamaciones' => ['max' => 1,  'window' => 1800],
                // Libro de Reclamaciones: per-IP daily cap and site-wide caps
                'reclamaciones_ip_day'    => ['max' => 5,  'window' => 86400],
                'reclamaciones_site_hour' => ['max' => 20, 'window' => 3600],
                'reclamaciones_site_day'  => ['max' => 80, 'window' => 86400],
            ],
        ],
        'anti_spam' => [
            // Minimum seconds between rendering and submitting the form
            'min_seconds'      => 3,
            // Libro de Reclamaciones: signed form token validity (seconds)
            'claims_min_seconds' => 5,
            'claims_max_seconds' => 7200,
            // Window used to detect repeated submissions
            'duplicate_window' => 86400,
            // Courtesy copies sent to the same mailbox per day
            'email_quota'      => ['max' => 5, 'window' => 86400],
        ],
        // hCaptcha (Libro de Reclamaciones). Keys live in config.smtp.php.
        'captcha'    => ['site_key' => '', 'secret' => '', 'allow_localhost' => false],
        'log_dir'    => __DIR__ . '/logs',
    ];

    $file = __DIR__ . '/config.smtp.php';
    $config = is_file($file) ? (array) require $file : [];
    $config = array_replace_recursive($defaults, $config);

    if (!is_dir($config['log_dir'])) {
        @mkdir($config['log_dir'], 0750, true);
    }

    return $config;
}

/* ── HTTP helpers ──────────────────────────────────────────── */

function yns_only_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        yns_json(['ok' => false, 'message' => 'Método no permitido.'], 405);
    }
}

/**
 * Reject cross-site posts. Privacy browsers may omit Origin/Referer,
 * so an absent header is accepted; a present but foreign host is not.
 */
function yns_same_origin(bool $require = false): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return true;
    }
    $host = preg_replace('/:\d+$/', '', $host);

    // Strict mode (claims form): a browser always sends Origin on a POST,
    // so a request with neither Origin nor Referer is not accepted.
    if ($require && empty($_SERVER['HTTP_ORIGIN']) && empty($_SERVER['HTTP_REFERER'])) {
        return false;
    }

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }
        $value = parse_url((string) $_SERVER[$key], PHP_URL_HOST);
        if ($value && strtolower($value) !== $host) {
            return false;
        }
    }
    return true;
}

function yns_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ── Input helpers ─────────────────────────────────────────── */

function yns_str(string $key, int $max = 4000): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        return '';
    }
    $value = strip_tags($value);
    $value = str_replace(["\0", "\r\n", "\r"], ["", "\n", "\n"], $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';
    // Collapse runs of blank lines but keep paragraph breaks.
    $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? '';
    return trim(mb_substr($value, 0, $max));
}

function yns_email(string $key): string
{
    $value = mb_strtolower(yns_str($key, 180));
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
}

function yns_check(string $key): bool
{
    $value = $_POST[$key] ?? '';
    return $value === '1' || $value === 'on' || $value === 'true' || $value === 'si';
}

function yns_honeypot(): bool
{
    // Field is hidden from humans; any content means a bot.
    return yns_str('website', 200) !== '' || yns_str('empresa_web', 200) !== '';
}

/**
 * Real client IP: REMOTE_ADDR, unless the connection comes from a Cloudflare
 * range, in which case CF-Connecting-IP is trusted. X-Forwarded-For is never used.
 */
function yns_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return '0.0.0.0';
    }

    $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && yns_in_cloudflare($ip)) {
        return $cf;
    }
    return $ip;
}

/** Ranges published by Cloudflare (https://www.cloudflare.com/ips/). */
function yns_in_cloudflare(string $ip): bool
{
    static $ranges = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    $bin = @inet_pton($ip);
    if ($bin === false) {
        return false;
    }
    foreach ($ranges as $cidr) {
        [$net, $bits] = explode('/', $cidr);
        $netBin = inet_pton($net);
        if (strlen($netBin) !== strlen($bin)) {
            continue;
        }
        $bits  = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (strncmp($bin, $netBin, $bytes) !== 0) {
            continue;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        if ((ord($bin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask)) {
            return true;
        }
    }
    return false;
}

/**
 * Server-side secret (generated once, stored in the deny-all logs directory).
 * Used to salt IP hashes and to sign the form token.
 */
function yns_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $file  = rtrim((string) yns_config()['log_dir'], '/\\') . '/.secret';
    $value = is_file($file) ? trim((string) @file_get_contents($file)) : '';
    if (strlen($value) < 32) {
        $new = bin2hex(random_bytes(32));
        $fp  = @fopen($file, 'x'); // fails if another request created it first
        if ($fp) {
            fwrite($fp, $new);
            fclose($fp);
            @chmod($file, 0600);
        }
        $value = trim((string) @file_get_contents($file));
        if (strlen($value) < 32) {
            $value = $new;
        }
    }
    return $secret = $value;
}

/** HMAC-signed issue time of the form: "<unix ts>.<signature>". */
function yns_form_token(): string
{
    $ts = (string) time();
    return $ts . '.' . hash_hmac('sha256', 'form|' . $ts, yns_secret());
}

/**
 * Validate the signed token posted with the form.
 * $why gets 'missing' | 'forged' | 'fast' | 'expired' for the internal log.
 */
function yns_form_token_ok(int $min, int $max, ?string &$why = null): bool
{
    $token = $_POST['form_token'] ?? '';
    if (!is_string($token) || !preg_match('/^(\d{9,12})\.([a-f0-9]{64})$/', $token, $m)) {
        $why = 'missing';
        return false;
    }
    if (!hash_equals(hash_hmac('sha256', 'form|' . $m[1], yns_secret()), $m[2])) {
        $why = 'forged';
        return false;
    }
    $elapsed = time() - (int) $m[1];
    if ($elapsed < $min) {
        $why = 'fast';
        return false;
    }
    if ($elapsed > $max) {
        $why = 'expired';
        return false;
    }
    return true;
}

/**
 * Names of the given fields that look like link spam: more than two URLs,
 * an HTML anchor or a BBCode [url=] tag (checked on the raw value, before
 * yns_str() strips tags).
 */
function yns_spam_fields(array $keys): array
{
    $bad = [];
    foreach ($keys as $key) {
        $value = $_POST[$key] ?? '';
        if (!is_string($value)) {
            continue;
        }
        if (preg_match_all('~(?:https?://|www\.)~i', $value) > 2
            || stripos($value, '<a ') !== false
            || stripos($value, '[url=') !== false) {
            $bad[] = $key;
        }
    }
    return $bad;
}

function yns_reference(string $prefix): string
{
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/* ── Rate limiting (file based, no database) ───────────────── */

/**
 * Resolve the limit rules for a bucket.
 * Buckets can be tuned per endpoint under rate_limit.buckets.
 */
function yns_limit_for(string $bucket): array
{
    $limits = (array) (yns_config()['rate_limit'] ?? []);
    $key    = strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $bucket));

    if ($key === 'global' && isset($limits['global'])) {
        $rule = (array) $limits['global'];
    } elseif ($key === 'daily' && isset($limits['daily'])) {
        $rule = (array) $limits['daily'];
    } else {
        $buckets = (array) ($limits['buckets'] ?? []);
        $rule    = isset($buckets[$key]) ? (array) $buckets[$key] : (array) $limits;
    }

    return [
        'max'     => (int) ($rule['max'] ?? 5),
        'window'  => (int) ($rule['window'] ?? 600),
        'message' => (string) ($rule['message'] ?? ''),
    ];
}

/**
 * Count one hit for the given key and tell whether it is over the limit.
 * A hit is only recorded when the caller is still below the limit.
 */
function yns_rate_limited(string $bucket, ?array $limit = null): bool
{
    $limit  = $limit ?: yns_limit_for($bucket);
    $max    = (int) ($limit['max'] ?? 5);
    $window = (int) ($limit['window'] ?? 600);
    if ($max <= 0) {
        return false;
    }

    // Only a salted hash of the IP is used (never the IP itself). 'per_ip' =>
    // false makes the bucket site-wide; 'fail_closed' => true rejects when the
    // counter cannot be read or locked (claims form).
    $who    = ($limit['per_ip'] ?? true) ? yns_client_ip() : 'site';
    $closed = (bool) ($limit['fail_closed'] ?? false);
    $file = rtrim((string) yns_config()['log_dir'], '/\\') . '/rl_' . hash_hmac('sha256', $bucket . '|' . $who, yns_secret()) . '.json';
    $now  = time();
    $fh   = @fopen($file, 'c+');
    if (!$fh) {
        return $closed; // leads are never blocked for a filesystem issue, except on the claims form
    }

    $hits = [];
    $limited = $closed;
    if (flock($fh, LOCK_EX)) {
        $raw  = stream_get_contents($fh) ?: '[]';
        $hits = json_decode($raw, true);
        $hits = is_array($hits) ? array_values(array_filter($hits, static fn ($t) => is_int($t) && $t > $now - $window)) : [];
        $limited = count($hits) >= $max;
        // 'peek' => true only checks the counter; the hit is not recorded.
        if (!$limited && empty($limit['peek'])) {
            $hits[] = $now;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($hits));
            fflush($fh);
        }
        flock($fh, LOCK_UN);
    }
    fclose($fh);

    return $limited;
}

/**
 * Cap the courtesy copies sent to a single mailbox, so the endpoint cannot
 * be used to spam a third party address.
 */
function yns_email_quota_exceeded(string $email, ?array $limit = null): bool
{
    $email = trim($email);
    if ($email === '') {
        return true;
    }

    $rules = (array) (yns_config()['anti_spam'] ?? []);
    $limit = $limit ?: (array) ($rules['email_quota'] ?? ['max' => 5, 'window' => 86400]);
    $max    = (int) ($limit['max'] ?? 5);
    $window = (int) ($limit['window'] ?? 86400);
    if ($max <= 0) {
        return false;
    }

    $file = rtrim((string) yns_config()['log_dir'], '/\\') . '/eq_' . sha1(strtolower($email)) . '.json';
    $now  = time();
    $fh   = @fopen($file, 'c+');
    if (!$fh) {
        return false;
    }

    $hits = [];
    if (flock($fh, LOCK_EX)) {
        $raw  = stream_get_contents($fh) ?: '[]';
        $hits = json_decode($raw, true);
        $hits = is_array($hits) ? array_values(array_filter($hits, static fn ($t) => is_int($t) && $t > $now - $window)) : [];
        $exceeded = count($hits) >= $max;
        if (!$exceeded) {
            $hits[] = $now;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($hits));
            fflush($fh);
        }
        flock($fh, LOCK_UN);
    }
    fclose($fh);

    return $exceeded ?? false;
}

/**
 * Look for a previous submission of the same type with the same values.
 * Used to avoid sending the same e-mail twice (and to avoid creating
 * duplicated hojas in the Libro de Reclamaciones).
 */
function yns_find_recent(string $type, array $match, ?int $window = null): ?array
{
    $rules  = (array) (yns_config()['anti_spam'] ?? []);
    $window = $window ?: (int) ($rules['duplicate_window'] ?? 86400);
    $dir    = rtrim((string) (yns_config()['log_dir'] ?? ''), '/\\');
    if ($dir === '' || $match === []) {
        return null;
    }

    $files = [];
    foreach ([date('Y-m'), date('Y-m', strtotime('-1 month'))] as $ym) {
        $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '-' . $ym . '.jsonl';
        if (is_file($file)) {
            $files[] = $file;
        }
    }

    $cutoff = time() - max(60, $window);
    foreach ($files as $file) {
        $handle = @fopen($file, 'r');
        if (!$handle) {
            continue;
        }
        $size   = (int) @filesize($file);
        $offset = max(0, $size - 524288); // read at most the last 512 KB
        fseek($handle, $offset);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $record = json_decode($line, true);
            if (!is_array($record)) {
                continue;
            }
            $created = strtotime((string) ($record['created_at'] ?? ''));
            if (!$created || $created < $cutoff) {
                continue;
            }
            $data = (array) ($record['data'] ?? []);
            $same = true;
            foreach ($match as $key => $value) {
                if (strtolower(trim((string) ($data[$key] ?? ''))) !== strtolower(trim((string) $value))) {
                    $same = false;
                    break;
                }
            }
            if ($same) {
                return $record;
            }
        }
    }

    return null;
}

/**
 * Bots usually submit a form in less than a second. The browser stamps the
 * form with a timestamp; requests without it (no JavaScript) are allowed.
 */
function yns_form_timing_ok(): bool
{
    $rules = (array) (yns_config()['anti_spam'] ?? []);
    $min   = (int) ($rules['min_seconds'] ?? 0);
    if ($min <= 0) {
        return true;
    }

    $ts = (int) ($_POST['ts'] ?? 0);
    if ($ts <= 0) {
        return true;
    }

    $elapsed = time() - (int) floor($ts / 1000);
    if ($elapsed < -120) {
        return true; // client clock ahead of the server: do not punish the user
    }

    return $elapsed >= $min;
}

/**
 * Reject oversized payloads before touching the mail transport.
 */
function yns_check_body_size(int $max = 65536): void
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > $max) {
        yns_json(['ok' => false, 'message' => 'La solicitud es demasiado grande.'], 413);
    }
}

/* ── Persistence + e-mail ──────────────────────────────────── */

function yns_store(string $type, array $data): void
{
    $config = yns_config();
    $dir    = rtrim($config['log_dir'], '/\\');
    $file   = $dir . '/' . $type . '-' . date('Y-m') . '.jsonl';
    $line   = json_encode(
        ['created_at' => date('c'), 'ip' => yns_client_ip(), 'type' => $type, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Render a branded HTML e-mail from a list of label => value pairs.
 */
function yns_email_html(string $heading, string $intro, array $rows, array $extra = []): string
{
    $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $body = '';
    foreach ($rows as $label => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $body .= '<tr>'
            . '<td style="padding:10px 14px;border-bottom:1px solid #e8eef5;font:600 12px/1.4 Arial,sans-serif;color:#64748b;text-transform:uppercase;letter-spacing:.04em;width:190px;vertical-align:top">' . $e((string) $label) . '</td>'
            . '<td style="padding:10px 14px;border-bottom:1px solid #e8eef5;font:400 14px/1.6 Arial,sans-serif;color:#0f172a;white-space:pre-wrap">' . nl2br($e((string) $value)) . '</td>'
            . '</tr>';
    }

    $foot = '';
    foreach ($extra as $label => $value) {
        $foot .= '<p style="margin:2px 0;font:400 11px/1.5 Arial,sans-serif;color:#94a3b8">' . $e((string) $label) . ': ' . $e((string) $value) . '</p>';
    }

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:24px;background:#f1f5f9">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">'
        . '<tr><td style="background:#0d1b35;padding:22px 26px">'
        . '<div style="font:800 18px/1.2 Arial,sans-serif;color:#ffffff;letter-spacing:.02em">YNSUMEDIC</div>'
        . '<div style="font:400 11px/1.4 Arial,sans-serif;color:#55c43a;letter-spacing:.12em;text-transform:uppercase;margin-top:4px">Sitio web corporativo</div>'
        . '</td></tr>'
        . '<tr><td style="padding:26px 26px 8px">'
        . '<h1 style="margin:0 0 6px;font:700 20px/1.3 Arial,sans-serif;color:#0f172a">' . $e($heading) . '</h1>'
        . '<p style="margin:0 0 18px;font:400 13px/1.6 Arial,sans-serif;color:#64748b">' . $e($intro) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e8eef5;border-radius:10px;border-collapse:separate;border-spacing:0">' . $body . '</table>'
        . '</td></tr>'
        . '<tr><td style="padding:16px 26px 24px">' . $foot . '</td></tr>'
        . '</table></body></html>';
}

function yns_send(string $subject, string $html, string $replyTo = '', string $replyName = '', string $to = ''): bool
{
    $config = yns_config();
    $to     = $to !== '' ? $to : (string) $config['to'];

    return Mailer::send(
        $to,
        (string) $config['to_name'],
        $subject,
        $html,
        (string) $config['from'],
        (string) $config['from_name'],
        $replyTo,
        $replyName
    );
}

/**
 * Common guard used by every endpoint.
 *
 * Order matters: transport level checks first, then the anti-spam layers,
 * then the honeypot, and only afterwards the field validation.
 */
function yns_guard(string $bucket, array $errors = [], array $options = []): void
{
    $strict = !empty($options['strict']);

    yns_only_post();
    yns_check_body_size();

    if (!yns_same_origin($strict)) {
        yns_json(['ok' => false, 'message' => 'Origen no permitido.'], 403);
    }

    /* Limits shared by every endpoint of the API: a short window and a hard daily
       cap per IP. On the claims form (strict) they are only CHECKED here and the
       hit is recorded after the captcha passes, so a failed captcha does not burn
       the visitor's allowance. Failed captchas have their own counter below. */
    $shared = [
        'global' => [
            yns_limit_for('global'),
            'Has enviado demasiadas solicitudes desde esta conexión. Espere un momento o escríbanos por WhatsApp.',
        ],
        'daily'  => [
            yns_limit_for('daily'),
            'Se alcanzó el límite diario de solicitudes desde esta conexión. Escríbanos por WhatsApp para atenderlo de inmediato.',
        ],
    ];
    foreach ($shared as $name => [$rule, $message]) {
        if (yns_rate_limited($name, $rule + ['peek' => $strict])) {
            yns_json(['ok' => false, 'message' => $message], 429);
        }
    }

    /* A form submitted in less than a few seconds is very likely a bot. */
    if ($strict) {
        /* Signed token issued by the server (GET ?token=1): the client cannot
           forge the fill time. Too fast: < claims_min_seconds; stale: > claims_max_seconds. */
        $rules = (array) (yns_config()['anti_spam'] ?? []);
        $why   = null;
        if (!yns_form_token_ok((int) ($rules['claims_min_seconds'] ?? 5), (int) ($rules['claims_max_seconds'] ?? 7200), $why)) {
            @error_log('[yns] ' . $bucket . ' token rejected (' . $why . ') from ' . yns_client_ip());
            yns_json([
                'ok'      => false,
                'message' => $why === 'fast'
                    ? 'El formulario se envió demasiado rápido. Recargue la página e intente nuevamente.'
                    : 'El formulario expiró. Recargue la página e intente nuevamente.',
            ], $why === 'fast' ? 429 : 400);
        }
    } elseif (!yns_form_timing_ok()) {
        yns_json([
            'ok'      => false,
            'message' => 'El formulario se envió demasiado rápido. Recargue la página e intente nuevamente.',
        ], 429);
    }

    /* Honeypot: pretend success so bots do not learn how to bypass it. */
    if (yns_honeypot()) {
        yns_json(['ok' => true, 'message' => 'Solicitud recibida.']);
    }

    /* Invalid submissions are rejected before touching the per endpoint
       quota, so a typo in the form does not consume the visitor allowance. */
    if ($errors !== []) {
        yns_json(['ok' => false, 'message' => 'Revise los datos marcados.', 'errors' => $errors], 422);
    }

    /* Claims form: hCaptcha (fail-closed) before the per endpoint quota is
       consumed and before any mail is sent. */
    if ($strict) {
        /* Failed captchas are throttled on their own counter (per IP), so a bot
           cannot hammer the verification call and a person is not locked out by
           a couple of mistakes. */
        $failRule = ['max' => 8, 'window' => 600, 'per_ip' => true, 'fail_closed' => true];
        if (yns_rate_limited('captcha_fail', $failRule + ['peek' => true])) {
            yns_json([
                'ok'      => false,
                'message' => 'Demasiados intentos de verificación. Espere unos minutos e intente nuevamente.',
            ], 429);
        }

        $captcha = new Captcha(yns_config(), 'ynsumedic.com');
        $why     = null;
        $posted  = $_POST['h-captcha-response'] ?? '';
        if (!$captcha->verify(is_string($posted) ? $posted : '', yns_client_ip(), $why)) {
            yns_rate_limited('captcha_fail', $failRule);
            @error_log('[yns] ' . $bucket . ' captcha rejected (' . $why . ') from ' . yns_client_ip());
            yns_json([
                'ok'      => false,
                'message' => 'No pudimos verificar que usted es una persona. Marque la casilla de verificación e intente nuevamente.',
            ], 400);
        }

        /* Captcha passed: only now count this submission against the shared limits. */
        foreach ($shared as $name => [$rule, $message]) {
            if (yns_rate_limited($name, $rule)) {
                yns_json(['ok' => false, 'message' => $message], 429);
            }
        }
    }

    /* Limit of this specific endpoint: only valid submissions count. */
    $rule = yns_limit_for($bucket);
    $rule['fail_closed'] = $strict;
    if (yns_rate_limited($bucket, $rule)) {
        yns_json([
            'ok'      => false,
            'message' => $rule['message'] !== ''
                ? $rule['message']
                : 'Ha enviado demasiadas solicitudes desde esta conexión. Espere unos minutos o escríbanos por WhatsApp.',
        ], 429);
    }

    /* Claims form: per-IP daily cap and site-wide caps against distributed spam. */
    if ($strict) {
        $caps = [
            [$bucket . '_ip_day',    true,  'Se alcanzó el límite diario de hojas desde esta conexión. Escríbanos por WhatsApp para atenderlo de inmediato.'],
            [$bucket . '_site_hour', false, 'El Libro de Reclamaciones recibió muchas solicitudes en este momento. Inténtelo más tarde o escríbanos por WhatsApp.'],
            [$bucket . '_site_day',  false, 'El Libro de Reclamaciones recibió muchas solicitudes en este momento. Inténtelo más tarde o escríbanos por WhatsApp.'],
        ];
        foreach ($caps as [$name, $perIp, $message]) {
            $cap = yns_limit_for($name);
            $cap['per_ip']      = $perIp;
            $cap['fail_closed'] = true;
            if (yns_rate_limited($name, $cap)) {
                yns_json(['ok' => false, 'message' => $message], 429);
            }
        }
    }
}
