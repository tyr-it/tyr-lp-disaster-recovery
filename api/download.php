<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$nome    = trim($body['nome'] ?? '');
$sobrenome = trim($body['sobrenome'] ?? '');
$empresa = trim($body['empresa'] ?? '');
$email   = trim($body['email'] ?? '');
$tel     = trim($body['tel'] ?? '');
$source  = trim($body['source'] ?? 'download-form');

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

// Log CSV
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);
$csv = $log_dir . '/leads.csv';
$new = !file_exists($csv);
$fp = fopen($csv, 'a');
if ($new) fputcsv($fp, ['data','nome','sobrenome','empresa','email','telefone','source']);
fputcsv($fp, [date('Y-m-d H:i:s'), $nome, $sobrenome, $empresa, $email, $tel, $source]);
fclose($fp);

// E-mail
$to = 'contato@tyr.com.br';
$subject = '[DR LP] Novo Lead — ' . $nome . ' | ' . $empresa;
$msg = "Novo lead — Disaster Recovery LP\n\n";
$msg .= "Nome: $nome $sobrenome\n";
$msg .= "Empresa: $empresa\n";
$msg .= "E-mail: $email\n";
$msg .= "Telefone: $tel\n";
$msg .= "Origem: $source\n";
$msg .= "Data: " . date('d/m/Y H:i:s') . "\n";
$headers = "From: noreply@tyr.digital\r\nReply-To: $email\r\nX-Mailer: TYR-DR-LP";
@mail($to, $subject, $msg, $headers);

// Confirmação ao lead
$conf_subject = 'Seu material sobre Disaster Recovery está a caminho — TYR';
$conf_msg = "Olá, $nome!\n\n";
$conf_msg .= "Obrigado pelo interesse na solução de Disaster Recovery da TYR + Acronis.\n";
$conf_msg .= "Você pode baixar a apresentação diretamente pelo link:\n";
$conf_msg .= "https://tyr.digital/lp/disaster-recovery/assets/docs/apresentacao-dr.pdf\n\n";
$conf_msg .= "Em breve um especialista nosso entrará em contato.\n";
$conf_msg .= "Qualquer dúvida, responda este e-mail ou nos chame no WhatsApp:\n";
$conf_msg .= "https://wa.me/551135880777\n\n";
$conf_msg .= "Atenciosamente,\nEquipe TYR\ncontato@tyr.com.br | +55 (11) 3588-0777";
$conf_headers = "From: TYR — IT Innovation Technology <noreply@tyr.digital>\r\nX-Mailer: TYR-DR-LP";
@mail($email, $conf_subject, $conf_msg, $conf_headers);

echo json_encode(['ok'=>true,'message'=>'Lead registered successfully']);
