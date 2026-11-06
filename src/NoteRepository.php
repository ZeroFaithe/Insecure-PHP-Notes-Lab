<?php
declare(strict_types=1);

/**
 * All note queries go through here. Every read/write is scoped to the
 * owner's user id, so one user can never see or change another user's note.
 */
final class NoteRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findOwned(int $noteId, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, user_id, title, body FROM notes WHERE id = ? AND user_id = ?');
        $stmt->execute([$noteId, $userId]);
        $note = $stmt->fetch();
        return $note ?: null;
    }

    public function create(int $userId, string $title, string $body): int
    {
        $stmt = $this->db->prepare('INSERT INTO notes (user_id, title, body) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $title, $body]);
        return (int) $this->db->lastInsertId();
    }

    /** Returns false if the note does not exist or is not owned by $userId. */
    public function update(int $noteId, int $userId, string $title, string $body): bool
    {
        if ($this->findOwned($noteId, $userId) === null) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$title, $body, $noteId, $userId]);
        return true;
    }

    public function delete(int $noteId, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
        $stmt->execute([$noteId, $userId]);
        return $stmt->rowCount() === 1;
    }
}
