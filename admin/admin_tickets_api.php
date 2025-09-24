<?php
// admin/admin_tickets_api.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../auth.php';             // controlli sessione/ruolo
require_role_api([1]);                        // SOLO admin -> 401 JSON se non autorizzato

require __DIR__ . '/../../secure/config.php'; // connessione DB (PDO)

function json_exit(array $payload, int $status = 200): void {
  http_response_code($status);
  echo json_encode($payload);
  exit;
}

try {
  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $action = $_GET['action'] ?? '';

  // Alias compatibili con il tuo stile precedente
  if ($action === 'list') $action = 'list_tickets';
  if ($action === 'view') $action = 'view_ticket';

  if ($method === 'GET') {
    if ($action === 'list_tickets') {
      $q = trim($_GET['q'] ?? '');
      $page = max(1, (int)($_GET['page'] ?? 1));
      $perPage = 20;
      $offset = ($page - 1) * $perPage;

      $where = '';
      $params = [];
      if ($q !== '') {
        $where = "WHERE (email LIKE :q OR oggetto LIKE :q OR messaggio LIKE :q)";
        $params[':q'] = "%{$q}%";
      }

      // Conteggio
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets {$where}");
      $stmt->execute($params);
      $total = (int)$stmt->fetchColumn();
      $pages = max(1, (int)ceil($total / $perPage));
      if ($page > $pages) { $page = $pages; $offset = ($page - 1) * $perPage; }

      // Lista
      $sql = "SELECT id, email, oggetto, messaggio, stato
              FROM tickets
              {$where}
              ORDER BY id DESC
              LIMIT :limit OFFSET :offset";
      $stmt = $pdo->prepare($sql);
      foreach ($params as $k=>$v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
      $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();
      $items = $stmt->fetchAll();

      json_exit(['ok'=>true, 'total'=>$total, 'page'=>$page, 'pages'=>$pages, 'items'=>$items]);
    }

    if ($action === 'view_ticket') {
      $id = (int)($_GET['id'] ?? 0);
      if ($id <= 0) json_exit(['ok'=>false, 'error'=>'ID mancante'], 400);

      $stmt = $pdo->prepare("SELECT id, email, oggetto, messaggio, stato FROM tickets WHERE id = :id");
      $stmt->execute([':id'=>$id]);
      $item = $stmt->fetch();
      if (!$item) json_exit(['ok'=>false, 'error'=>'Ticket non trovato'], 404);

      json_exit(['ok'=>true, 'item'=>$item]);
    }

    json_exit(['ok'=>false, 'error'=>'Azione non valida'], 400);
  }

  if ($method === 'POST') {
    // Body JSON
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = [];
    $action = $body['action'] ?? $action;

    if ($action === 'update_state') {
      $id = (int)($body['id'] ?? 0);
      $stato = (int)($body['stato'] ?? -1);
      if ($id <= 0 || ($stato !== 0 && $stato !== 1)) {
        json_exit(['ok'=>false, 'error'=>'Parametri non validi'], 400);
      }

      $stmt = $pdo->prepare("UPDATE tickets SET stato = :stato WHERE id = :id");
      $stmt->execute([':stato'=>$stato, ':id'=>$id]);
      json_exit(['ok'=>true]);
    }

    json_exit(['ok'=>false, 'error'=>'Azione non valida'], 400);
  }

  json_exit(['ok'=>false, 'error'=>'Metodo non consentito'], 405);

} catch (Throwable $e) {
  // error_log($e->getMessage());
  json_exit(['ok'=>false, 'error'=>'Errore server'], 500);
}
