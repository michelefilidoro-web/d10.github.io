<?php
// admin/admin_api.php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../auth.php';    // controlli sessione/ruolo
require_role_api([1]);               // SOLO admin, risponde 401 in JSON se non autorizzato

require __DIR__ . '/../../secure/config.php';  // connessione DB (PDO)

try {
  $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  $action = $_GET['action'] ?? 'list';

  if ($action === 'list') {
    $q = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = '';
    $params = [];
    if ($q !== '') {
      $where = "WHERE (nome LIKE :q OR email LIKE :q OR oggetto LIKE :q OR messaggio LIKE :q)";
      $params[':q'] = "%{$q}%";
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages {$where}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));

    $sql = "SELECT id, nome, email, oggetto, messaggio, ip, user_agent, created_at
            FROM contact_messages
            {$where}
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    echo json_encode(['ok'=>true, 'total'=>$total, 'page'=>$page, 'pages'=>$pages, 'items'=>$items]);
    exit;
  }

  if ($action === 'view') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
      http_response_code(400);
      echo json_encode(['ok'=>false, 'error'=>'ID mancante']);
      exit;
    }
    $stmt = $pdo->prepare("SELECT id, nome, email, oggetto, messaggio, ip, user_agent, created_at
                           FROM contact_messages WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    $item = $stmt->fetch();
    if (!$item) {
      http_response_code(404);
      echo json_encode(['ok'=>false, 'error'=>'Messaggio non trovato']);
      exit;
    }
    echo json_encode(['ok'=>true, 'item'=>$item]);
    exit;
  }

  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'Azione non valida']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>'Errore server']);
}
