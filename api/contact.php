<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$nome      = trim($body['nome'] ?? '');
$sobrenome = trim($body['sobrenome'] ?? '');
$email     = trim($body['email'] ?? '');
$tel       = trim($body['tel'] ?? '');
$mensagem  = trim($body['mensagem'] ?? '');

if (!$nome || !$email || !$mensagem) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Missing required fields']); exit; }
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

// Log CSV
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$csv = $log_dir . '/contacts.csv';
$new = !file_exists($csv);
$fp = fopen($csv, 'a');
if ($new) fputcsv($fp, ['data','nome','sobrenome','email','telefone','mensagem']);
fputcsv($fp, [date('Y-m-d H:i:s'), $nome, $sobrenome, $email, $tel, $mensagem]);
fclose($fp);

// E-mail de notificação para a TYR
$to      = 'contato@tyr.com.br';
$subject = "=?UTF-8?B?" . base64_encode("Fale Conosco — Disaster Recovery — $nome $sobrenome") . "?=";
$msg     = "Nova mensagem via LP Disaster Recovery:\n\n"
         . "Nome:     $nome $sobrenome\n"
         . "E-mail:   $email\n"
         . "Telefone: " . ($tel ?: '(não informado)') . "\n\n"
         . "Mensagem:\n$mensagem\n\n"
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
mail($to, $subject, $msg, $headers);

echo json_encode(['ok'=>true,'message'=>'Message received']);
