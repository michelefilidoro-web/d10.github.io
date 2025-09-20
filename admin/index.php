<?php
// /admin/index.php
require __DIR__ . '/../auth.php';
require_role([1]); // solo admin

header('Content-Type: text/html; charset=utf-8');

// mappa sicura delle pagine servibili
$pages = [
  'admin'           => __DIR__ . '/admin.html',
  'gestioneutenti'  => __DIR__ . '/gestioneutenti.html',
];

$page = $_GET['page'] ?? 'admin';
if (!isset($pages[$page])) {
  $page = 'admin';
}

readfile($pages[$page]);
