<?php
require __DIR__ . '/src/bootstrap.php';
install_error_handler(false);
session_start();

$db = db();
$notesRepo = new NoteRepository($db);
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

$action = $_POST['action'] ?? '';
if ($action === 'register') {
    $email = $_POST['email'];
    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'student'; // never trust a role sent by the client
    $stmt = $db->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
    $stmt->execute([$email, $hash, $role]);
    flash('Account created. You can sign in now.');
    redirect();
}

if ($action === 'login') {
    $email = $_POST['email'];
    $password = (string) ($_POST['password'] ?? '');
    $stmt = $db->prepare('SELECT id, role, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $re = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $re->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        redirect();
    }
    $message = 'Invalid email or password.';
}

if (in_array($action, ['save', 'update'], true) || isset($_GET['delete'])) {
    if ($userId === null) {
        http_response_code(401);
        exit('Please sign in.');
    }
}

if ($action === 'save') {
    // owner comes from the session, not the form
    $notesRepo->create($userId, (string) $_POST['title'], (string) $_POST['body']);
    redirect();
}

if ($action === 'update') {
    $ok = $notesRepo->update((int) $_POST['id'], $userId, (string) $_POST['title'], (string) $_POST['body']);
    if (!$ok) {
        http_response_code(404);
        exit('Note not found.');
    }
    redirect();
}

if (isset($_GET['delete'])) {
    $notesRepo->delete((int) $_GET['delete'], $userId);
    redirect();
}

if (isset($_GET['logout'])) {
    unset($_SESSION['user_id']);
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
