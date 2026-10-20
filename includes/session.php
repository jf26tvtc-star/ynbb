<?php
// تخزين جلسات PHP في قاعدة البيانات: على Vercel كل طلب قد يعمل على خادم مختلف،
// فلا يمكن الاعتماد على ملفات الجلسات المحلية (وإلا يُسجَّل خروج المدير عشوائيًا).

class YanbuDbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = yanbu_db()->prepare('SELECT data FROM sessions WHERE id = ? AND last_activity >= ?');
        $minActivity = time() - (int)ini_get('session.gc_maxlifetime');
        $stmt->bind_param('si', $id, $minActivity);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        return $row ? (string)$row[0] : '';
    }

    public function write(string $id, string $data): bool
    {
        $now = time();
        $stmt = yanbu_db()->prepare('INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = ?, last_activity = ?');
        $stmt->bind_param('ssisi', $id, $data, $now, $data, $now);
        return $stmt->execute();
    }

    public function destroy(string $id): bool
    {
        $stmt = yanbu_db()->prepare('DELETE FROM sessions WHERE id = ?');
        $stmt->bind_param('s', $id);
        return $stmt->execute();
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = yanbu_db()->prepare('DELETE FROM sessions WHERE last_activity < ?');
        $cutoff = time() - $max_lifetime;
        $stmt->bind_param('i', $cutoff);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public function validateId(string $id): bool
    {
        $stmt = yanbu_db()->prepare('SELECT 1 FROM sessions WHERE id = ?');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $stmt = yanbu_db()->prepare('UPDATE sessions SET last_activity = ? WHERE id = ?');
        $now = time();
        $stmt->bind_param('is', $now, $id);
        return $stmt->execute();
    }
}

function yanbu_use_db_sessions() {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', '7200');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    if ($https) ini_set('session.cookie_secure', '1');
    session_set_save_handler(new YanbuDbSessionHandler(), true);
}
