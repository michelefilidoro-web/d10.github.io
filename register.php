<?php
// register.php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Metodo non consentito']);
  exit;
}

require_once __DIR__ . '/../secure/config.php';

// Raccogli i campi dal form
$firstName      = trim($_POST['first_name'] ?? '');
$lastName       = trim($_POST['last_name'] ?? '');
$nomeUtenteForm = trim($_POST['nome_utente'] ?? ''); // opzionale
$email          = trim($_POST['email'] ?? '');
$password       = (string)($_POST['password'] ?? '');
$confirm        = (string)($_POST['confirm_password'] ?? '');
$ruolo          = 2;

// Componi il nome_utente se non è passato esplicitamente
$nome_utente = $nomeUtenteForm !== ''
  ? $nomeUtenteForm
  : trim($firstName . ' ' . $lastName);

// Validazioni base
if ($email === '' || $password === '' || $nome_utente === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Compila tutti i campi obbligatori']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Email non valida']);
  exit;
}

if (strlen($password) < 8) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'La password deve avere almeno 8 caratteri']);
  exit;
}

if ($confirm !== '' && $password !== $confirm) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Le password non coincidono']);
  exit;
}

try {
  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  // (opzionale ma esplicito) verifica univocità email
  $check = $pdo->prepare('SELECT 1 FROM utenti WHERE email = :email LIMIT 1');
  $check->execute([':email' => $email]);
  if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'Email già registrata']);
    exit;
  }

  // Hash password (bcrypt / default)
  $hash = password_hash($password, PASSWORD_DEFAULT);

  // Inserimento
  $stmt = $pdo->prepare('
    INSERT INTO utenti (nome_utente, email, password, ruolo)
    VALUES (:nome_utente, :email, :password, :ruolo)
  ');

  $stmt->execute([
    ':nome_utente' => $nome_utente,
    ':email'       => $email,
    ':password'    => $hash,
    ':ruolo'       => $ruolo,
  ]);

  // Se vuoi auto-loggare l’utente subito dopo la registrazione, decommenta il blocco seguente
  /*
  session_start();
  $userId = (int)$pdo->lastInsertId();
  $_SESSION['user_id'] = $userId;
  $_SESSION['nome']    = $nome_utente;
  $_SESSION['ruolo']   = $ruolo;

  // Reindirizzamento in base al ruolo (coerente con login.php)
  $redirect = '/';
  if ($ruolo == 1) {
    $redirect = '/admin/';
  } elseif ($ruolo == 2) {
    $redirect = '/utente/';
  } elseif ($ruolo == 3) {
    $redirect = '/avanzato/';
  }
  echo json_encode(['ok' => true, 'redirect' => $redirect]);
  exit;
  */

  // Altrimenti, rimanda al login
  echo json_encode(['ok' => true, 'redirect' => 'login.html']);
} catch (PDOException $e) {
  // Gestione chiave duplicata (se hai UNIQUE su email)
  if ($e->getCode() === '23000') {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'Email già registrata']);
    exit;
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore server']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore server']);
}
