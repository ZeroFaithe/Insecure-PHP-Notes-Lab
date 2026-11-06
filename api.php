<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
install_error_handler(true);
start_secure_session();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');

function json_response(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Authentication: the API uses the same session as the web app.
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
if ($userId === null) {
    json_response(401, ['error' => 'Authentication required']);
}

$notesRepo = new NoteRepository(db());

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Only the caller's own notes. user_id is kept for compatibility but must match.
    if (isset($_GET['user_id']) && (string) $_GET['user_id'] !== (string) $userId) {
        json_response(403, ['error' => 'Forbidden']);
    }
    json_response(200, $notesRepo->forUser($userId));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: validate JSON + ownership + csrf
    $data = json_decode(file_get_contents('php://input'), true);
    $ok = $notesRepo->update((int) $data['id'], $userId, $data['title'], $data['body']);
    json_response($ok ? 200 : 404, ['updated' => $ok ? 1 : 0]);
}

json_response(405, ['error' => 'Method not supported']);
