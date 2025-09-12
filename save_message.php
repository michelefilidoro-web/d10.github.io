<?php
// save_message.php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Metodo non consentito']);
  exit;
}

require_once __DIR__ . '/../secure/config.php';

// Validazione server-side di base
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$oggetto = trim($_POST['oggetto'] ?? '');
$messaggio = trim($_POST['messaggio'] ?? '');

if ($nome === '' || $email === '' || $oggetto === '' || $messaggio === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Compila tutti i campi']);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Email non valida']);
  exit;
}

// Anti-spam semplice: campo honeypot (non visibile all'utente)
if (!empty($_POST['website'])) { // se pieno, probabile bot
  http_response_code(200);
  echo json_encode(['ok' => true]); // finto ok
  exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

try {
  $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  $stmt = $pdo->prepare('
    INSERT INTO contact_messages (nome, email, oggetto, messaggio, ip, user_agent)
    VALUES (:nome, :email, :oggetto, :messaggio, :ip, :ua)
  ');
  $stmt->execute([
    ':nome' => $nome,
    ':email' => $email,
    ':oggetto' => $oggetto,
    ':messaggio' => $messaggio,
    ':ip' => $ip,
    ':ua' => $ua,
  ]);

  echo json_encode(['ok' => true, 'message' => 'Messaggio inviato!']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore server']);
}
