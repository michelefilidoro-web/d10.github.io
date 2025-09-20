<?php
// ticket_recupero.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Metodo non consentito']);
  exit;
}

// Carica config in modo robusto (adatta i percorsi alla tua struttura)
$cfgCandidates = [
  __DIR__ . '/../secure/config.php',
  dirname(__DIR__, 2) . '/secure/config.php',
];
$loaded = false;
foreach ($cfgCandidates as $cfg) {
  if (is_file($cfg)) { require_once $cfg; $loaded = true; break; }
}
if (!$loaded) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Config non trovata']);
  exit;
}

$email     = trim($_POST['email'] ?? '');
$oggetto   = trim($_POST['oggetto'] ?? 'Recupero password');
$messaggio = trim($_POST['messaggio'] ?? '');

if ($email === '' || $messaggio === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Compila email e messaggio']);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Email non valida']);
  exit;
}

// (facoltativo) piccola protezione da flood: lunghezza minima messaggio
if (mb_strlen($messaggio) < 5) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Messaggio troppo corto']);
  exit;
}

try {
  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // Inserisci ticket (stato 1 = aperto)
  $stmt = $pdo->prepare('
    INSERT INTO tickets (email, oggetto, messaggio, stato)
    VALUES (:email, :oggetto, :messaggio, 1)
  ');
  $stmt->execute([
    ':email'     => $email,
    ':oggetto'   => $oggetto,
    ':messaggio' => $messaggio,
  ]);

  echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore server']);
}
