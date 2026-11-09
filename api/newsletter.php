<?php
/**
 * POST /api/newsletter.php — blog subscription.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$errors = [];

$email = yns_email('email');

if ($email === '') {
    $errors['email'] = 'Ingrese un correo electrónico válido.';
}
if (!yns_check('privacidad')) {
    $errors['privacidad'] = 'Debe aceptar la Política de Privacidad para suscribirse.';
}

yns_guard('newsletter', $errors);

$data = [
    'email' => $email,
    'page'  => yns_str('page', 200),
];

yns_store('newsletter', $data);

$config = yns_config();
$sent = yns_send(
    'Nueva suscripción al boletín — ' . $email,
    yns_email_html(
        'Nueva suscripción al boletín',
        'Un visitante se suscribió a las actualizaciones de normativa y artículos técnicos.',
        [
            'Correo'   => $email,
            'Recibido' => date('d/m/Y H:i'),
        ],
        ['IP' => yns_client_ip(), 'Página' => $data['page'] !== '' ? $data['page'] : 'No indicada']
    )
);

if (!$sent) {
    yns_json([
        'ok'      => false,
        'message' => 'No pudimos completar la suscripción en este momento. Inténtelo nuevamente en unos minutos.',
    ], 502);
}

$copiaEnviada = true;
if (!empty($config['autoreply'])) {
    $copiaEnviada = Mailer::send(
        $email,
        '',
        'YNSUMEDIC — Suscripción confirmada',
        yns_email_html(
            'Suscripción confirmada',
            'Recibirá nuestros artículos sobre salud ocupacional, normativa y equipamiento médico corporativo.',
            ['Correo registrado' => $email],
            ['Puede solicitar la baja respondiendo a este mensaje.']
        ),
        (string) $config['from'],
        (string) $config['from_name'],
        (string) $config['to'],
        (string) $config['to_name']
    );
}

yns_json([
    'ok'       => true,
    'copySent' => $copiaEnviada,
    'message'  => 'Suscripción confirmada. Gracias por escribirnos.',
]);
