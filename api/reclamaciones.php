<?php
/**
 * POST /api/reclamaciones.php — Libro de Reclamaciones Virtual.
 * Ley N° 29571 (Código de Protección y Defensa del Consumidor) y
 * D.S. N° 011-2011-PCM (Reglamento del Libro de Reclamaciones).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$errors = [];

$tipo = yns_str('tipo', 20);
if (!in_array($tipo, ['reclamo', 'queja'], true)) {
    $errors['tipo'] = 'Seleccione si su caso es un reclamo o una queja.';
}

$nombre    = yns_str('nombre', 160);
$docType   = strtoupper(yns_str('doc_type', 20));
$docNumber = preg_replace('/[^A-Za-z0-9]/', '', yns_str('doc_number', 20));
$domicilio = yns_str('domicilio', 220);
$telefono  = yns_str('telefono', 30);
$email     = yns_email('email');

if (mb_strlen($nombre) < 3) {
    $errors['nombre'] = 'Ingrese su nombre completo.';
}
if (!in_array($docType, ['DNI', 'CE', 'PASAPORTE', 'RUC'], true)) {
    $errors['doc_type'] = 'Seleccione el tipo de documento.';
} elseif ($docType === 'DNI' && !preg_match('/^\d{8}$/', $docNumber)) {
    $errors['doc_number'] = 'El DNI debe tener 8 dígitos.';
} elseif ($docType === 'RUC' && !preg_match('/^\d{11}$/', $docNumber)) {
    $errors['doc_number'] = 'El RUC debe tener 11 dígitos.';
} elseif ($docType !== 'DNI' && $docType !== 'RUC' && !preg_match('/^[A-Za-z0-9]{6,20}$/', $docNumber)) {
    $errors['doc_number'] = 'Ingrese un número de documento válido.';
}
if (mb_strlen($domicilio) < 5) {
    $errors['domicilio'] = 'Ingrese su domicilio completo.';
}
if (!preg_match('/^[\d\s\+\-\(\)]{7,16}$/', $telefono)) {
    $errors['telefono'] = 'Ingrese un teléfono válido.';
}
if ($email === '') {
    $errors['email'] = 'Ingrese un correo electrónico válido para enviarle la constancia.';
}

$esMenor      = yns_check('es_menor');
$representante = yns_str('representante', 160);
$repDoc        = preg_replace('/[^A-Za-z0-9]/', '', yns_str('rep_doc', 20));
if ($esMenor && (mb_strlen($representante) < 3 || strlen($repDoc) < 6)) {
    $errors['representante'] = 'Si el consumidor es menor de edad, indique el nombre y documento del representante.';
}

$bienType = yns_str('bien_type', 20);
if (!in_array($bienType, ['producto', 'servicio'], true)) {
    $errors['bien_type'] = 'Indique si el caso se refiere a un producto o a un servicio.';
}

$bienDesc   = yns_str('bien_desc', 300);
$monto      = yns_str('monto', 30);
$comprobante = yns_str('comprobante', 80);
$detalle    = yns_str('detalle', 5000);
$pedido     = yns_str('pedido', 3000);

if (mb_strlen($bienDesc) < 3) {
    $errors['bien_desc'] = 'Describa el producto o servicio contratado.';
}
if ($monto !== '' && !preg_match('/^\d{1,9}([.,]\d{1,2})?$/', $monto)) {
    $errors['monto'] = 'Ingrese un monto válido (solo números y decimales).';
}
if (mb_strlen($detalle) < 20) {
    $errors['detalle'] = 'Detalle lo ocurrido (mínimo 20 caracteres).';
}
if (mb_strlen($pedido) < 10) {
    $errors['pedido'] = 'Indique qué solución espera obtener.';
}
if (!yns_check('veracidad')) {
    $errors['veracidad'] = 'Debe declarar la veracidad de la información.';
}
if (!yns_check('privacidad')) {
    $errors['privacidad'] = 'Debe aceptar la Política de Privacidad.';
}

yns_guard('reclamaciones', $errors);

/* ── Persist + notify ──────────────────────────────────────── */

$referencia = yns_reference('LIB');
$data = [
    'referencia'      => $referencia,
    'tipo'            => $tipo,
    'nombre'          => $nombre,
    'doc_type'        => $docType,
    'doc_number'      => $docNumber,
    'domicilio'       => $domicilio,
    'telefono'        => $telefono,
    'email'           => $email,
    'es_menor'        => $esMenor ? 'sí' : 'no',
    'representante'   => $representante,
    'rep_doc'         => $repDoc,
    'bien_type'       => $bienType,
    'bien_desc'       => $bienDesc,
    'monto'           => $monto,
    'comprobante'     => $comprobante,
    'detalle'         => $detalle,
    'pedido'          => $pedido,
    'page'            => yns_str('page', 200),
];

yns_store('reclamaciones', $data);

$rows = [
    'Constancia'        => $referencia,
    'Tipo'              => $tipo === 'reclamo' ? 'RECLAMO' : 'QUEJA',
    'Consumidor'        => $nombre,
    'Documento'         => $docType . ' ' . $docNumber,
    'Domicilio'         => $domicilio,
    'Teléfono'          => $telefono,
    'Correo'            => $email,
    'Menor de edad'     => $esMenor ? 'Sí' : 'No',
    'Representante'     => $representante !== '' ? $representante . ' (' . $repDoc . ')' : '',
    'Bien o servicio'   => $bienType === 'producto' ? 'Producto' : 'Servicio',
    'Descripción'       => $bienDesc,
    'Monto reclamado'   => $monto,
    'Comprobante'       => $comprobante,
    'Detalle del caso'  => $detalle,
    'Pedido concreto'   => $pedido,
];

$sent = yns_send(
    'Libro de Reclamaciones — ' . strtoupper($tipo) . ' — ' . $referencia,
    yns_email_html(
        'Nueva hoja del Libro de Reclamaciones',
        'Registro recibido por el canal virtual. La respuesta debe emitirse dentro del plazo máximo de 30 días calendario (Ley N° 29571).',
        $rows,
        [
            'Recibido' => date('d/m/Y H:i') . ' (hora del servidor)',
            'IP'       => yns_client_ip(),
        ]
    ),
    $email,
    $nombre
);

if (!$sent) {
    yns_json([
        'ok'      => false,
        'message' => 'No pudimos registrar el envío por correo en este momento. Su constancia quedó almacenada; escríbanos para confirmar la recepción.',
    ], 502);
}

/* ── Acknowledgement with the reference number ─────────────── */

$config = yns_config();
if (!empty($config['autoreply'])) {
    Mailer::send(
        $email,
        $nombre,
        'YNSUMEDIC — Constancia del Libro de Reclamaciones ' . $referencia,
        yns_email_html(
            'Constancia de recepción',
            'Su hoja fue registrada en el Libro de Reclamaciones Virtual de YNSUMEDIC. Conserve el número de constancia para el seguimiento de su caso.',
            [
                'Constancia' => $referencia,
                'Tipo'       => $tipo === 'reclamo' ? 'Reclamo' : 'Queja',
                'Consumidor' => $nombre,
                'Bien o servicio' => $bienDesc,
            ],
            [
                'Plazo de respuesta: máximo 30 días calendario.',
                'Este mensaje se generó automáticamente; no responda a esta dirección.',
            ]
        ),
        (string) $config['from'],
        (string) $config['from_name'],
        (string) $config['to'],
        (string) $config['to_name']
    );
}

yns_json([
    'ok'         => true,
    'referencia' => $referencia,
    'message'    => 'Hoja registrada. Enviamos la constancia a su correo electrónico.',
]);
