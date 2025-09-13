<?php
// login.php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Metodo non consentito']);
  exit;
}

require_once __DIR__ . '/../secure/config.php';

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Inserisci email e password']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Email non valida']);
  exit;
}

try {
  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  $stmt = $pdo->prepare('SELECT id, nome_utente, password, ruolo FROM utenti WHERE email = :email');
  $stmt->execute([':email' => $email]);
  $user = $stmt->fetch();

  if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Credenziali errate']);
    exit;
  }

  // verifica password (qui si suppone sia salvata con password_hash)
  if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Credenziali errate']);
    exit;
  }

  // Se arrivo qui -> login valido
  session_start();
  $_SESSION['user_id'] = $user['id'];
  $_SESSION['nome'] = $user['nome_utente'];
  $_SESSION['ruolo'] = $user['ruolo'];

  // Reindirizzamento in base al ruolo
  $redirect = '/'; // fallback alla homepage
  if ($user['ruolo'] == 1) {
    $redirect = '/admin/';     // cartella admin, che contiene index.php (il gate)
  } elseif ($user['ruolo'] == 2) {
    $redirect = '/utente/';    // stessa logica, se un giorno crei la cartella utente/
  } elseif ($user['ruolo'] == 3) {
    $redirect = '/avanzato/';  // idem
  }

  echo json_encode(['ok' => true, 'redirect' => $redirect]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore server']);
}
