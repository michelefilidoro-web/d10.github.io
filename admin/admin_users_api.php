<?php
// admin_users_api.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ---- Utilities ----
function jexit(array $payload, int $status = 200): void {
  http_response_code($status);
  echo json_encode($payload);
  exit;
}

function require_admin(): array {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
  if (empty($_SESSION['user_id'])) {
    jexit(['ok' => false, 'error' => 'Non autenticato'], 401);
  }
  $uid = (int)$_SESSION['user_id'];
  $role = (int)($_SESSION['ruolo'] ?? 0);
  if ($role !== 1) {
    jexit(['ok' => false, 'error' => 'Non autorizzato'], 403);
  }
  return ['id' => $uid, 'ruolo' => $role];
}

function get_pdo(): PDO {
  require_once __DIR__ . '/../../secure/config.php';
  $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
  // Nota: lasciamo EMULATE_PREPARES = false, e NON bindiamo LIMIT/OFFSET
  return new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function sanitize_int($v, int $min, int $max, int $default): int {
  $i = filter_var($v, FILTER_VALIDATE_INT);
  if ($i === false) return $default;
  return max($min, min($max, $i));
}

// ---- Inizio ----
$me = require_admin();
$pdo = get_pdo();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
  if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action !== 'list_users') {
      jexit(['ok' => false, 'error' => 'Azione non valida'], 400);
    }

    $page = sanitize_int($_GET['page'] ?? 1, 1, 100000, 1);
    $perPage = sanitize_int($_GET['per_page'] ?? 10, 1, 50, 10);
    $q = trim((string)($_GET['q'] ?? ''));

    // WHERE + parametri
    $where = '1=1';
    $params = [];
    if ($q !== '') {
      $where .= ' AND (nome_utente LIKE :q OR email LIKE :q)';
      $params[':q'] = '%' . $q . '%';
    }

    // Conteggio
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utenti WHERE $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    // Query dati — interpoliamo interi già sanificati
    $sql = "SELECT id, nome_utente, email, ruolo
            FROM utenti
            WHERE $where
            ORDER BY id DESC
            LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->execute();
    $items = $stmt->fetchAll();

    jexit([
      'ok'    => true,
      'page'  => $page,
      'pages' => $pages,
      'total' => $total,
      'items' => $items,
    ]);
  }

  if ($method === 'POST') {
    $body = read_json_body();
    $action = $body['action'] ?? '';

    $validRoles = [1, 2, 3];

    $is_last_admin = function(int $userIdToDemote) use ($pdo): bool {
      $countAdmins = (int)$pdo->query("SELECT COUNT(*) FROM utenti WHERE ruolo = 1")->fetchColumn();
      if ($countAdmins <= 1) {
        $stmt = $pdo->prepare("SELECT ruolo FROM utenti WHERE id = :id");
        $stmt->execute([':id' => $userIdToDemote]);
        return ((int)$stmt->fetchColumn() === 1);
      }
      return false;
    };

    if ($action === 'update_role') {
      $id = sanitize_int($body['id'] ?? 0, 1, PHP_INT_MAX, 0);
      $ruolo = sanitize_int($body['ruolo'] ?? 0, 1, 3, 0);

      if ($id <= 0 || !in_array($ruolo, $validRoles, true)) {
        jexit(['ok' => false, 'error' => 'Parametri non validi'], 400);
      }

      // Evita declassamento dell'unico admin
      if ($ruolo !== 1 && $is_last_admin($id)) {
        jexit(['ok' => false, 'error' => 'Operazione negata: non puoi declassare l’unico admin'], 409);
      }

      $stmt = $pdo->prepare("UPDATE utenti SET ruolo = :ruolo WHERE id = :id");
      $stmt->execute([':ruolo' => $ruolo, ':id' => $id]);

      jexit(['ok' => true]);
    }

    if ($action === 'bulk_update_roles') {
      $items = $body['items'] ?? [];
      if (!is_array($items) || empty($items)) {
        jexit(['ok' => false, 'error' => 'Nessuna modifica inviata'], 400);
      }

      $results = [];
      $updated = 0;

      $pdo->beginTransaction();
      try {
        foreach ($items as $row) {
          $id = sanitize_int($row['id'] ?? 0, 1, PHP_INT_MAX, 0);
          $ruolo = sanitize_int($row['ruolo'] ?? 0, 1, 3, 0);

          if ($id <= 0 || !in_array($ruolo, $validRoles, true)) {
            $results[] = ['id' => $id, 'ok' => false, 'error' => 'Parametri non validi'];
            continue;
          }

          if ($ruolo !== 1 && $is_last_admin($id)) {
            $results[] = ['id' => $id, 'ok' => false, 'error' => 'Non puoi declassare l’unico admin'];
            continue;
          }

          $stmt = $pdo->prepare("UPDATE utenti SET ruolo = :ruolo WHERE id = :id");
          $stmt->execute([':ruolo' => $ruolo, ':id' => $id]);

          $updated++;
          $results[] = ['id' => $id, 'ok' => true];
        }

        $pdo->commit();
      } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('bulk_update_roles: ' . $e->getMessage());
        jexit(['ok' => false, 'error' => 'Errore transazione'], 500);
      }

      jexit(['ok' => true, 'updated' => $updated, 'results' => $results]);
    }

    jexit(['ok' => false, 'error' => 'Azione non valida'], 400);
  }

  jexit(['ok' => false, 'error' => 'Metodo non consentito'], 405);

} catch (Throwable $e) {
  // Per debug (log su server)
  error_log('admin_users_api.php: ' . $e->getMessage());
  jexit(['ok' => false, 'error' => 'Errore server'], 500);
}
