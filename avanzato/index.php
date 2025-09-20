<?php
// admin/index.php
require __DIR__ . '/../auth.php';   // usa auth.php dalla cartella superiore
require_role([3]);                  // consenti solo ruolo 1 (admin)

// Se arrivi qui sei autorizzato: servi l'HTML dell'area admin
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/avanzato.html');
