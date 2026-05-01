<?php
/**
 * BANKIVA — Procesador de formulario de contacto
 * Recibe JSON via POST, valida, envía email HTML al equipo y auto-respuesta al visitante.
 *
 * REEMPLAZAR: cambiar $DESTINATARIO a contacto@bankiva.com.ar cuando el dominio esté activo
 * REEMPLAZAR: cambiar $FROM_DOMAIN a bankiva.com.ar (debe coincidir con el dominio del hosting)
 */

const DESTINATARIO = 'agustinmoscato369@gmail.com';
const FROM_DOMAIN  = 'bankiva.com.ar';  /* Cambiar al dominio real del hosting */
const WA_LINK      = 'https://wa.me/5492326400516';

/* ── Headers de respuesta ── */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/* Sólo POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

/* ── Leer cuerpo (JSON o form-encoded) ── */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

/* ── Sanitización ── */
function limpiar(string $valor): string {
    return htmlspecialchars(strip_tags(trim($valor)), ENT_QUOTES, 'UTF-8');
}

$nombre  = limpiar($data['nombre']  ?? '');
$empresa = limpiar($data['empresa'] ?? '');
$pais    = limpiar($data['pais']    ?? '');
$mensaje = limpiar($data['mensaje'] ?? '');
$email   = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);

/* ── Validación ── */
$errores = [];
if ($nombre  === '') $errores[] = 'El nombre es requerido.';
if ($empresa === '') $errores[] = 'La empresa es requerida.';
if ($mensaje === '') $errores[] = 'El mensaje es requerido.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El email ingresado no es válido.';
}

if (!empty($errores)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => implode(' ', $errores)]);
    exit;
}

/* ── Plantilla de email para el equipo ── */
$asunto_equipo = "Nueva consulta BANKIVA — {$nombre} ({$empresa})";

$pais_fila = $pais !== '' ? "
      <tr>
        <td class='lbl'>País</td>
        <td>{$pais}</td>
      </tr>" : '';

$html_equipo = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body  { margin:0; padding:0; background:#f0f4f8; font-family:Arial,sans-serif; color:#1A1A2E; }
    .wrap { max-width:580px; margin:32px auto; border-radius:10px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.10); }
    .hdr  { background:#2AACB8; padding:28px 36px; }
    .hdr h1 { margin:0; color:#fff; font-size:20px; font-weight:700; }
    .hdr p  { margin:6px 0 0; color:rgba(255,255,255,.75); font-size:14px; }
    .body { background:#fff; padding:36px; }
    table { width:100%; border-collapse:collapse; margin-bottom:24px; }
    td   { padding:12px 0; border-bottom:1px solid #E8EAED; font-size:15px; vertical-align:top; }
    .lbl { width:120px; font-weight:700; color:#4A5568; font-size:13px; text-transform:uppercase; letter-spacing:.05em; }
    .msg  { background:#F7F8FA; border-left:4px solid #2AACB8; padding:18px 20px; border-radius:0 6px 6px 0; line-height:1.75; font-size:15px; white-space:pre-wrap; word-break:break-word; margin-bottom:24px; }
    .reply{ display:inline-block; background:#2AACB8; color:#fff; text-decoration:none; padding:13px 26px; border-radius:6px; font-weight:700; font-size:14px; }
    .foot { background:#F7F8FA; padding:20px 36px; text-align:center; font-size:12px; color:#9AA3B0; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hdr">
      <h1>Nueva consulta desde la web</h1>
      <p>Recibiste un mensaje a través de bankiva.com.ar</p>
    </div>
    <div class="body">
      <table>
        <tr>
          <td class="lbl">Nombre</td>
          <td>{$nombre}</td>
        </tr>
        <tr>
          <td class="lbl">Empresa</td>
          <td>{$empresa}</td>
        </tr>{$pais_fila}
        <tr>
          <td class="lbl">Email</td>
          <td><a href="mailto:{$email}" style="color:#2AACB8">{$email}</a></td>
        </tr>
      </table>

      <div class="msg">{$mensaje}</div>

      <a href="mailto:{$email}" class="reply">Responder a {$nombre} →</a>
    </div>
    <div class="foot">Enviado desde bankiva.com.ar · BANKIVA Consultoría Avícola</div>
  </div>
</body>
</html>
HTML;

/* ── Plantilla de auto-respuesta para el visitante ── */
$asunto_visitante = '¡Recibimos tu consulta! — BANKIVA Consultoría Avícola';

$html_visitante = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body  { margin:0; padding:0; background:#f0f4f8; font-family:Arial,sans-serif; color:#1A1A2E; }
    .wrap { max-width:560px; margin:32px auto; border-radius:10px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.10); }
    .hdr  { background:#2AACB8; padding:28px 36px; }
    .hdr h1 { margin:0; color:#fff; font-size:20px; font-weight:700; }
    .body { background:#fff; padding:36px; font-size:16px; line-height:1.75; }
    .body p { margin:0 0 16px; color:#4A5568; }
    .wa   { display:inline-block; background:#25D366; color:#fff; text-decoration:none; padding:13px 26px; border-radius:6px; font-weight:700; font-size:14px; margin-top:8px; }
    .foot { background:#F7F8FA; padding:20px 36px; text-align:center; font-size:12px; color:#9AA3B0; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="hdr">
      <h1>¡Gracias por contactarnos, {$nombre}!</h1>
    </div>
    <div class="body">
      <p>Recibimos tu consulta y te responderemos a la brevedad.</p>
      <p>Si necesitás una respuesta urgente, podés escribirnos directamente por WhatsApp:</p>
      <a href="{WA_LINK}" class="wa">WhatsApp: +54 9 2326 400516</a>
      <p style="margin-top:28px;color:#9AA3B0;font-size:13px">— Equipo BANKIVA Consultoría Avícola</p>
    </div>
    <div class="foot">BANKIVA Consultoría Avícola · bankiva.com.ar</div>
  </div>
</body>
</html>
HTML;

$html_visitante = str_replace('{WA_LINK}', WA_LINK, $html_visitante);

/* ── Función para armar headers de email ── */
function headers_html(string $from_name, string $from_email, string $reply_email = ''): string {
    $h  = "MIME-Version: 1.0\r\n";
    $h .= "Content-Type: text/html; charset=UTF-8\r\n";
    $h .= "From: {$from_name} <{$from_email}>\r\n";
    if ($reply_email !== '') {
        $h .= "Reply-To: {$reply_email}\r\n";
    }
    $h .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";
    return $h;
}

/* ── Enviar email al equipo ── */
$ok = mail(
    DESTINATARIO,
    $asunto_equipo,
    $html_equipo,
    headers_html('BANKIVA Web', 'no-reply@' . FROM_DOMAIN, $email)
);

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al enviar el mensaje. Por favor intentá de nuevo o escribinos por WhatsApp.']);
    exit;
}

/* ── Auto-respuesta al visitante (no bloqueante) ── */
mail(
    $email,
    $asunto_visitante,
    $html_visitante,
    headers_html('BANKIVA Consultoría Avícola', 'no-reply@' . FROM_DOMAIN)
);

echo json_encode(['success' => true]);
