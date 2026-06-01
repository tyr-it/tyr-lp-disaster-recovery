<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://tyr.digital');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Honeypot — bot preenche, humano não
if (!empty($body['website'])) {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

function sanitize(string $v, int $max = 200): string {
    return substr(strip_tags(trim($v)), 0, $max);
}

$nome      = sanitize($body['nome']      ?? '');
$sobrenome = sanitize($body['sobrenome'] ?? '');
$empresa   = sanitize($body['empresa']   ?? '');
$email     = sanitize($body['email']     ?? '', 254);
$tel       = sanitize($body['tel']       ?? '', 30);

$allowed = ['download-form', 'mini-form'];
$source  = in_array($body['source'] ?? '', $allowed) ? $body['source'] : 'download-form';

if (!$nome || !$email || !$empresa) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid email']);
    exit;
}

$blocked_domains = [
    'gmail.com','gmail.com.br','googlemail.com',
    'hotmail.com','hotmail.com.br','hotmail.co.uk',
    'outlook.com','outlook.com.br','live.com','live.com.br',
    'yahoo.com','yahoo.com.br',
    'bol.com.br','ig.com.br','uol.com.br','terra.com.br',
    'icloud.com','me.com','mac.com',
    'protonmail.com','proton.me',
    'yandex.com','mail.ru','msn.com','zoho.com',
    'aol.com','gmx.com','zipmail.com.br',
    'globo.com','r7.com',
];

$domain = strtolower(substr(strrchr($email, '@'), 1));
if (in_array($domain, $blocked_domains)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Personal email not allowed']);
    exit;
}

// Proteção contra header injection no Reply-To
$email_header = str_replace(["\r", "\n"], '', $email);

// Log CSV
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) @mkdir($log_dir, 0750, true);
$csv = $log_dir . '/leads.csv';
$new = !file_exists($csv);
$fp  = fopen($csv, 'a');
if ($fp) {
    if ($new) fputcsv($fp, ['data', 'nome', 'sobrenome', 'empresa', 'email', 'telefone', 'source']);
    fputcsv($fp, [date('Y-m-d H:i:s'), $nome, $sobrenome, $empresa, $email, $tel, $source]);
    fclose($fp);
}

// E-mail via relay Postfix (smtp.vivasol.com.br configurado no servidor)
$to      = 'contato@tyr.com.br';
$subject = "=?UTF-8?B?" . base64_encode("Lead — Download Disaster Recovery — $nome $sobrenome | $empresa") . "?=";
$body_txt = "Novo download da apresentação Disaster Recovery:\n\n"
          . "Nome:     $nome $sobrenome\n"
          . "Empresa:  $empresa\n"
          . "E-mail:   $email\n"
          . "Telefone: " . ($tel ?: '(não informado)') . "\n"
          . "Origem:   $source\n\n"
          . "Data/Hora: " . date('d/m/Y H:i:s') . " (UTC)\n"
          . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido') . "\n\n"
          . "---\n"
          . "TYR — Landing Page Disaster Recovery\n"
          . "https://tyr.digital/lp/disaster-recovery";

$headers  = "From: Site TYR <contato.tyr@vivasol.com.br>\r\n";
$headers .= "Reply-To: $email_header\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";

mail($to, $subject, $body_txt, $headers);

echo json_encode(['ok' => true, 'message' => 'Lead registered successfully']);
