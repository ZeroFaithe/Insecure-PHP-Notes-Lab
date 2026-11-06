<?php
declare(strict_types=1);

// Never show errors to the browser; log them instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/NoteRepository.php';

/**
 * Generic error handler: log the details server-side, show a generic message.
 */
function install_error_handler(bool $json): void
{
    set_exception_handler(function (Throwable $e) use ($json): void {
        error_log(sprintf('[campus-notes] %s in %s:%d', get_class($e), basename($e->getFile()), $e->getLine()));
        if (!headers_sent()) {
            http_response_code(500);
        }
        if ($json) {
            echo json_encode(['error' => 'Internal server error']);
        } else {
            echo 'Something went wrong. Please try again later.';
        }
    });
}
