<?php
// logout.php
require __DIR__ . '/auth.php';

// Svuota la sessione
$_SESSION = [];

// Cancella il cookie di sessione se esiste
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params['path'], $params['domain'],
    $params['secure'], $params['httponly']
  );
}

// Distruggi la sessione
session_destroy();

// Reindirizza alla homepage
header('Location: /index.html');
exit;
