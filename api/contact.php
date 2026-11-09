<?php
/**
 * POST /api/contact.php — corporate quote requests.
 * Returns JSON: {ok:true, referencia} or {ok:false, message, errors}.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

yns_only_post();

if (!yns_same_origin()) {
    yns_json(['ok' => false, 'message' => 'Origen no permitido.'], 403);
}

/* ── Validation ────────────────────────────────────────────── */

$errors = [];

$empresa       = yns_str('empresa', 160);
$rucRaw        = yns_str('ruc', 20);
$ruc           = preg_replace('/\D/', '', $rucRaw);
$nombre        = yns_str('nombre', 120);
$cargo         = yns_str('cargo', 120);
$phone         = yns_str('phone', 30);
$email         = yns_email('email');
$servicio      = yns_str('servicio', 120);
$requerimiento = yns_str('requerimiento', 4000);

if (mb_strlen($empresa) < 2) {
    $errors['empresa'] = 'Ingrese el nombre o razón social de su empresa.';
}
if (!preg_match('/^\d{11}$/', $ruc)) {
    $errors['ruc'] = 'El RUC debe tener 11 dígitos.';
}
if (mb_strlen($nombre) < 3) {
    $errors['nombre'] = 'Ingrese su nombre completo.';
}
if (!preg_match('/^[\d\s\+\-\(\)]{7,16}$/', $phone)) {
    $errors['phone'] = 'Ingrese un teléfono válido (7 a 15 dígitos).';
}
if ($email === '') {
    $errors['email'] = 'Ingrese un correo electrónico válido.';
}
if (mb_strlen($requerimiento) < 10) {
    $errors['requerimiento'] = 'Describa su requerimiento (mínimo 10 caracteres).';
}
if (!yns_check('privacidad')) {
    $errors['privacidad'] = 'Debe aceptar la Política de Privacidad para continuar.';
}

yns_guard('contact', $errors);

/* ── Duplicate protection ──────────────────────────────────── */

$previous = yns_find_recent('contacto', ['email' => $email, 'ruc' => $ruc]);
if ($previous !== null) {
    yns_json([
        'ok'         => true,
        'referencia' => (string) ($previous['data']['referencia'] ?? ''),
        'duplicate'  => true,
        'message'    => 'Ya recibimos una solicitud con estos datos. No es necesario enviarla otra vez; le responderemos en breve.',
    ]);
}

/* ── Persist + notify ──────────────────────────────────────── */

$referencia = yns_reference('COT');
$data = [
    'referencia'    => $referencia,
    'empresa'       => $empresa,
    'ruc'           => $ruc,
    'nombre'        => $nombre,
    'cargo'         => $cargo,
    'phone'         => $phone,
    'email'         => $email,
    'servicio'      => $servicio,
    'requerimiento' => $requerimiento,
    'page'          => yns_str('page', 200),
];

yns_store('contacto', $data);

$rows = [
    'Referencia'    => $referencia,
    'Empresa'       => $empresa,
    'RUC'           => $ruc,
    'Contacto'      => $nombre . ($cargo !== '' ? ' — ' . $cargo : ''),
    'Teléfono'      => $phone,
    'Correo'        => $email,
    'Requerimiento' => $servicio !== '' ? $servicio : 'No especificado',
    'Detalle'       => $requerimiento,
];

$sent = yns_send(
    'Nueva cotización web [' . $referencia . ']' . ($servicio !== '' ? ' — ' . $servicio : ''),
    yns_email_html(
        'Nueva solicitud de cotización',
        'Se recibió un requerimiento corporativo desde el sitio web. Responder al correo del contacto antes de 24 horas hábiles.',
        $rows,
        [
            'Recibido' => date('d/m/Y H:i') . ' (hora del servidor)',
            'IP'       => yns_client_ip(),
            'Página'   => $data['page'] !== '' ? $data['page'] : 'No indicada',
        ]
    ),
    $email,
    $nombre
);

if (!$sent) {
    yns_json([
        'ok'      => false,
        'message' => 'No pudimos completar el envío en este momento. Su solicitud quedó registrada; escríbanos por WhatsApp para confirmarla.',
    ], 502);
}

/* Courtesy copy for the submitter */
$config = yns_config();
$copiaEnviada = true;
if (!empty($config['autoreply'])) {
    if (yns_email_quota_exceeded($email)) {
        /* Daily quota for this mailbox reached: keep the lead, skip the copy. */
        $copiaEnviada = false;
    } else {
        $copia = yns_email_html(
            'Recibimos su solicitud',
            'Gracias por escribirnos. Un especialista de YNSUMEDIC revisará su requerimiento y le responderá en un plazo máximo de 24 horas hábiles.',
            [
                'Referencia' => $referencia,
                'Empresa'    => $empresa,
                'Requerimiento' => $servicio !== '' ? $servicio : 'Consulta corporativa',
            ],
            ['Este mensaje se generó automáticamente; no responda a esta dirección.']
        );
        $copiaEnviada = Mailer::send(
            $email,
            $nombre,
            'YNSUMEDIC — Recibimos su solicitud ' . $referencia,
            $copia,
            (string) $config['from'],
            (string) $config['from_name'],
            (string) $config['to'],
            (string) $config['to_name']
        );
    }
}

yns_json([
    'ok'         => true,
    'referencia' => $referencia,
    'copySent'   => $copiaEnviada,
    'message'    => 'Solicitud enviada. Un especialista le responderá en menos de 24 horas hábiles.',
]);
