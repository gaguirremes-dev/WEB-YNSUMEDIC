<?php
/**
 * YNSUMEDIC — shared helpers for the public API endpoints.
 * No framework, no dependencies. PHP 8.0+.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/Mailer.php';

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
        'transport'  => 'mail',
        'smtp'       => [],
        'autoreply'  => true,
        'rate_limit' => ['max' => 5, 'window' => 600],
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
function yns_same_origin(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return true;
    }
    $host = preg_replace('/:\d+$/', '', $host);

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
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ── Input helpers ─────────────────────────────────────────── */

function yns_str(string $key, int $max = 4000): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
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

function yns_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function yns_reference(string $prefix): string
{
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/* ── Rate limiting (file based, no database) ───────────────── */

function yns_rate_limited(string $bucket): bool
{
    $config = yns_config();
    $max    = (int) ($config['rate_limit']['max'] ?? 5);
    $window = (int) ($config['rate_limit']['window'] ?? 600);
    if ($max <= 0) {
        return false;
    }

    $file = rtrim($config['log_dir'], '/\\') . '/rl_' . sha1($bucket . '|' . yns_client_ip()) . '.json';
    $now  = time();
    $fh   = @fopen($file, 'c+');
    if (!$fh) {
        return false; // never block a lead because of a filesystem issue
    }

    $hits = [];
    if (flock($fh, LOCK_EX)) {
        $raw  = stream_get_contents($fh) ?: '[]';
        $hits = json_decode($raw, true);
        $hits = is_array($hits) ? array_values(array_filter($hits, static fn ($t) => is_int($t) && $t > $now - $window)) : [];
        $limited = count($hits) >= $max;
        if (!$limited) {
            $hits[] = $now;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($hits));
            fflush($fh);
        }
        flock($fh, LOCK_UN);
    }
    fclose($fh);

    return $limited ?? false;
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
 */
function yns_guard(string $bucket, array $errors = []): void
{
    yns_only_post();

    if (!yns_same_origin()) {
        yns_json(['ok' => false, 'message' => 'Origen no permitido.'], 403);
    }
    if (yns_honeypot()) {
        // Pretend success so bots do not learn how to bypass the filter.
        yns_json(['ok' => true, 'message' => 'Solicitud recibida.']);
    }
    if (yns_rate_limited($bucket)) {
        yns_json([
            'ok'      => false,
            'message' => 'Hemos recibido varias solicitudes desde su conexión. Espere unos minutos o escríbanos por WhatsApp.',
        ], 429);
    }
    if ($errors !== []) {
        yns_json(['ok' => false, 'message' => 'Revise los datos marcados.', 'errors' => $errors], 422);
    }
}
