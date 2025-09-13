<?php
// auth.php

// Impostazioni sicure per la sessione (da fare prima di session_start)
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
// Se il sito gira in HTTPS, scommenta la riga sotto
// ini_set('session.cookie_secure', 1);

session_start();

/**
 * Verifica se l'utente è loggato
 */
function is_logged_in(): bool {
  return isset($_SESSION['user_id']);
}

/**
 * Restituisce il ruolo attuale o null
 */
function current_role() {
  return $_SESSION['ruolo'] ?? null;
}

/**
 * Reindirizza al login se non loggato
 */
function require_login(): void {
  if (!is_logged_in()) {
    $target = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: /login.php?redirect=' . urlencode($target));
    exit;
  }
}

/**
 * Richiede che l'utente abbia uno dei ruoli passati
 */
function require_role(array $roles): void {
  if (!is_logged_in() || !in_array(current_role(), $roles, true)) {
    $target = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: /login.php?redirect=' . urlencode($target));
    exit;
  }
}

/**
 * Variante per API: se non autorizzato, risponde 401 JSON invece di redirect
 */
function require_role_api(array $roles): void {
  if (!is_logged_in() || !in_array(current_role(), $roles, true)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
  }
}
