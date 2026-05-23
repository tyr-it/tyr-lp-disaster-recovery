<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$nome      = trim($body['nome']      ?? '');
$sobrenome = trim($body['sobrenome'] ?? '');
$empresa   = trim($body['empresa']   ?? '');
$email     = trim($body['email']     ?? '');
$tel       = trim($body['tel']       ?? '');
$source    = trim($body['source']    ?? 'download-form');

if (!$nome || !$email || !$empresa) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Missing required fields']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Invalid email']); exit; }

$blocked = ['gmail','hotmail','yahoo','outlook','live','bol','icloud','uol','terra','ig.com','protonmail','zohomail'];
$domain = strtolower(explode('@', $email)[1] ?? '');
foreach ($blocked as $b) {
    if (strpos($domain, $b) !== false) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Personal email not allowed']);
        exit;
    }
}

// Sanitização
$nome      = htmlspecialchars($nome,      ENT_QUOTES, 'UTF-8');
$sobrenome = htmlspecialchars($sobrenome, ENT_QUOTES, 'UTF-8');
$empresa   = htmlspecialchars($empresa,   ENT_QUOTES, 'UTF-8');
$tel       = htmlspecialchars($tel,       ENT_QUOTES, 'UTF-8');

// Log CSV
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$csv = $log_dir . '/leads.csv';
$new = !file_exists($csv);
$fp = fopen($csv, 'a');
if ($new) fputcsv($fp, ['data','nome','sobrenome','empresa','email','telefone','source']);
fputcsv($fp, [date('Y-m-d H:i:s'), $nome, $sobrenome, $empresa, $email, $tel, $source]);
fclose($fp);

// E-mail via relay Postfix (smtp.vivasol.com.br configurado no servidor)
$to      = 'contato@tyr.com.br';
$subject = "=?UTF-8?B?" . base64_encode("Lead — Download Disaster Recovery — $nome $sobrenome | $empresa") . "?=";
$body    = "Novo download da apresentação Disaster Recovery:\n\n"
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
$headers .= "Reply-To: $email\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";

mail($to, $subject, $body, $headers);

echo json_encode(['ok'=>true,'message'=>'Lead registered successfully']);
