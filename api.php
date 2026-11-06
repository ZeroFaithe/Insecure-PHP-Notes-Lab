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
    // TODO: csrf / origin
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw === false || strlen($raw) > 65536) {
        json_response(413, ['error' => 'Request body too large']);
    }
    try {
        $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_response(400, ['error' => 'Malformed JSON']);
    }
    if (!is_array($data) || array_is_list($data)) {
        json_response(400, ['error' => 'Expected a JSON object']);
    }
    $allowed = ['id', 'title', 'body'];
    if (array_diff(array_keys($data), $allowed)) {
        json_response(400, ['error' => 'Unexpected fields; only id, title and body are accepted']);
    }
    $id = $data['id'] ?? null;
    if (!is_int($id) || $id < 1) {
        json_response(422, ['error' => 'id must be a positive integer']);
    }
    $fields = validate_note($data['title'] ?? null, $data['body'] ?? null);
    if ($fields === null) {
        json_response(422, ['error' => 'title (1-120 chars) and body (1-10000 chars) are required strings']);
    }
    // Per-record authorization: update only succeeds for the caller's own note.
    // 404 (not 403) so the API does not reveal which ids exist.
    if (!$notesRepo->update($id, $userId, ...$fields)) {
        json_response(404, ['error' => 'Note not found']);
    }
    json_response(200, ['updated' => 1]);
}

json_response(405, ['error' => 'Method not supported']);
