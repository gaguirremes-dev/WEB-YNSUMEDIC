<?php
/**
 * Minimal dependency-free mailer: SMTP (SSL/STARTTLS + AUTH LOGIN),
 * PHP mail() fallback, or a local log transport for testing.
 */

declare(strict_types=1);

final class Mailer
{
    public static function send(
        string $to,
        string $toName,
        string $subject,
        string $html,
        string $from,
        string $fromName,
        string $replyTo = '',
        string $replyName = ''
    ): bool {
        $config    = self::config();
        $transport = strtolower((string) ($config['transport'] ?? 'mail'));

        if ($transport === 'log') {
            return self::writeLog($to, $subject, $html);
        }

        if ($transport === 'smtp') {
            try {
                self::smtp($to, $toName, $subject, $html, $from, $fromName, $replyTo, $replyName, (array) ($config['smtp'] ?? []));
                return true;
            } catch (Throwable $e) {
                self::error($e->getMessage());
                return false;
            }
        }

        return self::phpMail($to, $toName, $subject, $html, $from, $fromName, $replyTo, $replyName);
    }

    private static function config(): array
    {
        return function_exists('yns_config') ? yns_config() : [];
    }

    /* ── Transport: PHP mail() ─────────────────────────────── */

    private static function phpMail(
        string $to,
        string $toName,
        string $subject,
        string $html,
        string $from,
        string $fromName,
        string $replyTo,
        string $replyName
    ): bool {
        $headers = self::headers($from, $fromName, $replyTo, $replyName);
        $headers .= "\r\nContent-Type: text/html; charset=UTF-8";
        $headers .= "\r\nContent-Transfer-Encoding: base64";

        $body = chunk_split(base64_encode($html), 76, "\r\n");
        $ok   = @mail(self::formatAddress($to, $toName), self::encodeHeader($subject), $body, $headers);
        if (!$ok) {
            self::error('mail() returned false');
        }
        return $ok;
    }

    /* ── Transport: SMTP ───────────────────────────────────── */

    private static function smtp(
        string $to,
        string $toName,
        string $subject,
        string $html,
        string $from,
        string $fromName,
        string $replyTo,
        string $replyName,
        array $smtp
    ): void {
        $host    = (string) ($smtp['host'] ?? '');
        $port    = (int) ($smtp['port'] ?? 465);
        $secure  = strtolower((string) ($smtp['secure'] ?? 'ssl'));
        $user    = (string) ($smtp['user'] ?? '');
        $pass    = (string) ($smtp['pass'] ?? '');
        $timeout = (int) ($smtp['timeout'] ?? 15);

        if ($host === '') {
            throw new RuntimeException('SMTP host is empty');
        }

        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp     = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            throw new RuntimeException('SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
        }
        stream_set_timeout($fp, $timeout);

        try {
            self::expect($fp, [220]);
            self::command($fp, 'EHLO ' . self::hostname(), [250]);

            if ($secure === 'tls') {
                self::command($fp, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed');
                }
                self::command($fp, 'EHLO ' . self::hostname(), [250]);
            }

            if ($user !== '') {
                self::command($fp, 'AUTH LOGIN', [334]);
                self::command($fp, base64_encode($user), [334]);
                self::command($fp, base64_encode($pass), [235]);
            }

            self::command($fp, 'MAIL FROM:<' . self::cleanAddress($from) . '>', [250]);
            self::command($fp, 'RCPT TO:<' . self::cleanAddress($to) . '>', [250, 251]);
            self::command($fp, 'DATA', [354]);

            $data = 'To: ' . self::formatAddress($to, $toName) . "\r\n"
                . 'From: ' . self::formatAddress($from, $fromName) . "\r\n"
                . self::headers($from, $fromName, $replyTo, $replyName)
                . 'Subject: ' . self::encodeHeader($subject) . "\r\n"
                . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
                . 'Content-Transfer-Encoding: base64' . "\r\n"
                . "\r\n"
                . rtrim(chunk_split(base64_encode($html), 76, "\r\n")) . "\r\n.\r\n";

            fwrite($fp, $data);
            self::expect($fp, [250]);
            self::command($fp, 'QUIT', [221]);
        } finally {
            fclose($fp);
        }
    }

    private static function command($fp, string $command, array $expected): void
    {
        fwrite($fp, $command . "\r\n");
        self::expect($fp, $expected);
    }

    private static function expect($fp, array $expected): void
    {
        $lines = [];
        while (($line = fgets($fp, 515)) !== false) {
            $line  = rtrim($line, "\r\n");
            $lines[] = $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        if ($lines === []) {
            throw new RuntimeException('SMTP: empty response');
        }
        $code = (int) substr(end($lines), 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('SMTP: expected ' . implode('/', $expected) . ', got ' . $code . ' (' . implode(' | ', $lines) . ')');
        }
    }

    /* ── Helpers ───────────────────────────────────────────── */

    private static function headers(string $from, string $fromName, string $replyTo, string $replyName): string
    {
        $domain = self::domain($from);
        $headers = 'Date: ' . date('r') . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $domain . '>' . "\r\n"
            . 'MIME-Version: 1.0' . "\r\n"
            . 'X-Mailer: YNSUMEDIC Web' . "\r\n";

        if ($replyTo !== '') {
            $headers .= 'Reply-To: ' . self::formatAddress($replyTo, $replyName) . "\r\n";
        }
        return $headers;
    }

    private static function formatAddress(string $email, string $name): string
    {
        $email = self::cleanAddress($email);
        if ($name === '') {
            return $email;
        }
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    private static function encodeHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function cleanAddress(string $email): string
    {
        return (string) preg_replace('/[^A-Za-z0-9@._+-]/', '', $email);
    }

    private static function hostname(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'ynsumedic.com');
        $host = preg_replace('/:\d+$/', '', $host) ?: 'ynsumedic.com';
        return preg_replace('/^www\./', '', $host) ?: 'ynsumedic.com';
    }

    private static function domain(string $email): string
    {
        $parts = explode('@', $email);
        return $parts[1] ?? 'ynsumedic.com';
    }

    private static function writeLog(string $to, string $subject, string $html): bool
    {
        $config = self::config();
        $dir    = rtrim((string) ($config['log_dir'] ?? sys_get_temp_dir()), '/\\');
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $entry = '=== ' . date('c') . ' | to: ' . $to . ' | subject: ' . $subject . " ===\n" . $html . "\n";
        return @file_put_contents($dir . '/mail-' . date('Y-m') . '.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function error(string $message): void
    {
        $config = self::config();
        $dir    = rtrim((string) ($config['log_dir'] ?? sys_get_temp_dir()), '/\\');
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents(
            $dir . '/mail-error.log',
            '[' . date('c') . '] ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
