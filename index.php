<?php
require __DIR__ . '/src/bootstrap.php';
install_error_handler(false);
start_secure_session();

$db = db();
$notesRepo = new NoteRepository($db);
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$action = $isPost ? (string) ($_POST['action'] ?? '') : '';

// Every state-changing request must be a POST with a valid CSRF token.
if ($isPost && !csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid or missing CSRF token. Reload the page and try again.');
}
if ($action === 'register') {
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    if ($email === false || strlen($email) > 254 || strlen($password) < 12 || strlen($password) > 72) {
        $message = 'Enter a valid email and a password of 12 to 72 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'student'; // never trust a role sent by the client
        try {
            $stmt = $db->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
            $stmt->execute([$email, $hash, $role]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') { // 23000 = duplicate email
                throw $e;
            }
        }
        // Same message either way so registration can't be used to list accounts.
        flash('If the address was not already registered, the account was created. You can sign in now.');
        redirect();
    }
}

if ($action === 'login') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = $db->prepare('SELECT id, role, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $re = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $re->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        login_session((int) $user['id'], (string) $user['role']);
        redirect();
    }
    $message = 'Invalid email or password.';
}

if (in_array($action, ['save', 'update', 'delete'], true)) {
    if ($userId === null) {
        http_response_code(401);
        exit('Please sign in.');
    }
}

if ($action === 'save' || $action === 'update') {
    $fields = validate_note($_POST['title'] ?? null, $_POST['body'] ?? null);
    if ($fields === null) {
        http_response_code(422);
        exit('Title (max 120) and body (max 10000) are required.');
    }
}

if ($action === 'save') {
    // owner comes from the session, not the form
    $notesRepo->create($userId, ...$fields);
    redirect();
}

if ($action === 'update') {
    $ok = $notesRepo->update((int) ($_POST['id'] ?? 0), $userId, ...$fields);
    if (!$ok) {
        http_response_code(404);
        exit('Note not found.');
    }
    redirect();
}

if ($action === 'delete') {
    if (!$notesRepo->delete((int) ($_POST['id'] ?? 0), $userId)) {
        http_response_code(404);
        exit('Note not found.');
    }
    redirect();
}

if ($action === 'logout') {
    end_session();
    redirect();
}

$message = $message ?? flash();
$notes = [];
$editing = false;
if ($userId !== null) {
    $notes = $notesRepo->forUser($userId);
    if (isset($_GET['edit'])) {
        $editing = $notesRepo->findOwned((int) $_GET['edit'], $userId) ?? false;
    }
}

send_security_headers();
render('home', [
    'message' => $message,
    'userId' => $userId,
    'role' => $_SESSION['role'] ?? '',
    'notes' => $notes,
    'editing' => $editing,
]);
