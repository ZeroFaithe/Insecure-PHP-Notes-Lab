<?php
declare(strict_types=1);

const SESSION_IDLE_TIMEOUT = 1800; // 30 minutes

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
}

function start_secure_session(): void
{
    ini_set('session.use_strict_mode', '1');   // reject uninitialized (attacker-chosen) IDs
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('CAMPUSNOTESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https(),   // always true in production behind HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    // Idle timeout
    $now = time();
    if (isset($_SESSION['last_seen']) && $now - $_SESSION['last_seen'] > SESSION_IDLE_TIMEOUT) {
        end_session();
        session_start();
    }
    $_SESSION['last_seen'] = $now;
}

/** Call right after a successful login to prevent session fixation. */
function login_session(int $userId, string $role): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    unset($_SESSION['csrf_token']); // new token for the new privilege level
}

/** Fully destroy the session on the server and in the browser. */
function end_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'],
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}
