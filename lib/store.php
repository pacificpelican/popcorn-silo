<?php
declare(strict_types=1);

/*
 * Storage layer: a small Store interface with two implementations —
 * MySqlStore (PDO) and JsonStore (local files). store() picks MySQL
 * when it is configured and reachable, and falls back to JSON otherwise.
 */

interface Store {
    /** Human-readable description for the debug panel. */
    public function describe(): string;

    public function findUserByEmail(string $email): ?array;
    public function createUser(string $email, string $passwordHash): array;
    public function getUser(int $id): ?array;

    /** Remove a user and every file row they own. */
    public function deleteUser(int $id): void;

    public function createFile(int $userId, string $originalName, string $storedName, int $size, string $mime): array;
    public function getFile(int $id): ?array;
    public function listFilesByUser(int $userId): array;
    public function renameFile(int $id, string $newName): void;
    public function setShared(int $id, bool $shared): void;
    public function deleteFile(int $id): void;
    public function mostRecentSharedFile(): ?array;
}

/* ---------------- Local JSON store ---------------- */

final class JsonStore implements Store {
    private string $dir;

    public function __construct(string $dir) {
        $this->dir = rtrim($dir, '/\\');
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        foreach (['users', 'files'] as $name) {
            if (!file_exists($this->path($name))) {
                file_put_contents($this->path($name), '[]', LOCK_EX);
            }
        }
    }

    public function describe(): string {
        return 'Local JSON (' . $this->dir . ')';
    }

    private function path(string $name): string {
        return $this->dir . '/' . $name . '.json';
    }

    private function read(string $name): array {
        $data = json_decode((string)file_get_contents($this->path($name)), true);
        return is_array($data) ? $data : [];
    }

    private function write(string $name, array $rows): void {
        file_put_contents($this->path($name), json_encode(array_values($rows), JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function nextId(array $rows): int {
        $max = 0;
        foreach ($rows as $r) {
            $max = max($max, (int)$r['id']);
        }
        return $max + 1;
    }

    public function findUserByEmail(string $email): ?array {
        foreach ($this->read('users') as $u) {
            if (strtolower((string)$u['email']) === strtolower($email)) {
                return $u;
            }
        }
        return null;
    }

    public function createUser(string $email, string $passwordHash): array {
        $users = $this->read('users');
        $user = [
            'id'            => $this->nextId($users),
            'email'         => $email,
            'password_hash' => $passwordHash,
            'created_at'    => gmdate('Y-m-d H:i:s'),
        ];
        $users[] = $user;
        $this->write('users', $users);
        return $user;
    }

    public function getUser(int $id): ?array {
        foreach ($this->read('users') as $u) {
            if ((int)$u['id'] === $id) {
                return $u;
            }
        }
        return null;
    }

    public function deleteUser(int $id): void {
        $users = array_filter($this->read('users'), fn($u) => (int)$u['id'] !== $id);
        $this->write('users', $users);
        $files = array_filter($this->read('files'), fn($f) => (int)$f['user_id'] !== $id);
        $this->write('files', $files);
    }

    public function createFile(int $userId, string $originalName, string $storedName, int $size, string $mime): array {
        $files = $this->read('files');
        $file = [
            'id'            => $this->nextId($files),
            'user_id'       => $userId,
            'original_name' => $originalName,
            'stored_name'   => $storedName,
            'size'          => $size,
            'mime'          => $mime,
            'shared'        => false,
            'created_at'    => gmdate('Y-m-d H:i:s'),
        ];
        $files[] = $file;
        $this->write('files', $files);
        return $file;
    }

    public function getFile(int $id): ?array {
        foreach ($this->read('files') as $f) {
            if ((int)$f['id'] === $id) {
                return $f;
            }
        }
        return null;
    }

    public function listFilesByUser(int $userId): array {
        $out = array_filter($this->read('files'), fn($f) => (int)$f['user_id'] === $userId);
        usort($out, fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']) ?: ((int)$b['id'] <=> (int)$a['id']));
        return $out;
    }

    private function updateFile(int $id, callable $mutate): void {
        $files = $this->read('files');
        foreach ($files as &$f) {
            if ((int)$f['id'] === $id) {
                $mutate($f);
                break;
            }
        }
        unset($f);
        $this->write('files', $files);
    }

    public function renameFile(int $id, string $newName): void {
        $this->updateFile($id, function (&$f) use ($newName) { $f['original_name'] = $newName; });
    }

    public function setShared(int $id, bool $shared): void {
        $this->updateFile($id, function (&$f) use ($shared) { $f['shared'] = $shared; });
    }

    public function deleteFile(int $id): void {
        $files = array_filter($this->read('files'), fn($f) => (int)$f['id'] !== $id);
        $this->write('files', $files);
    }

    public function mostRecentSharedFile(): ?array {
        $shared = array_filter($this->read('files'), fn($f) => !empty($f['shared']));
        usort($shared, fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']) ?: ((int)$b['id'] <=> (int)$a['id']));
        return $shared[0] ?? null;
    }
}

/* ---------------- MySQL store ---------------- */

final class MySqlStore implements Store {
    private PDO $pdo;
    private string $database;
    private string $host;

    public function __construct(array $cfg) {
        $this->host = (string)$cfg['host'];
        $this->database = (string)$cfg['database'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'], (int)$cfg['port'], $cfg['database']);
        $this->pdo = new PDO($dsn, (string)$cfg['username'], (string)$cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->migrate();
    }

    public function describe(): string {
        return 'mySQL';
    }

    private function migrate(): void {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                size BIGINT NOT NULL DEFAULT 0,
                mime VARCHAR(255) NOT NULL DEFAULT "",
                shared TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_files_user (user_id),
                INDEX idx_files_shared (shared),
                CONSTRAINT fk_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function findUserByEmail(string $email): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function createUser(string $email, string $passwordHash): array {
        $stmt = $this->pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
        $stmt->execute([$email, $passwordHash]);
        return $this->getUser((int)$this->pdo->lastInsertId());
    }

    public function getUser(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function deleteUser(int $id): void {
        // files rows are removed by the ON DELETE CASCADE foreign key.
        $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public function createFile(int $userId, string $originalName, string $storedName, int $size, string $mime): array {
        $stmt = $this->pdo->prepare('INSERT INTO files (user_id, original_name, stored_name, size, mime) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $originalName, $storedName, $size, $mime]);
        return $this->getFile((int)$this->pdo->lastInsertId());
    }

    public function getFile(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $row['shared'] = (bool)$row['shared'];
        }
        return $row ?: null;
    }

    public function listFilesByUser(int $userId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE user_id = ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$userId]);
        return array_map(function ($r) {
            $r['shared'] = (bool)$r['shared'];
            return $r;
        }, $stmt->fetchAll());
    }

    public function renameFile(int $id, string $newName): void {
        $this->pdo->prepare('UPDATE files SET original_name = ? WHERE id = ?')->execute([$newName, $id]);
    }

    public function setShared(int $id, bool $shared): void {
        $this->pdo->prepare('UPDATE files SET shared = ? WHERE id = ?')->execute([$shared ? 1 : 0, $id]);
    }

    public function deleteFile(int $id): void {
        $this->pdo->prepare('DELETE FROM files WHERE id = ?')->execute([$id]);
    }

    public function mostRecentSharedFile(): ?array {
        $row = $this->pdo->query('SELECT * FROM files WHERE shared = 1 ORDER BY created_at DESC, id DESC LIMIT 1')->fetch();
        if ($row) {
            $row['shared'] = (bool)$row['shared'];
        }
        return $row ?: null;
    }
}

/* ---------------- Factory ---------------- */

/** Build the store: MySQL when configured and reachable, local JSON otherwise. */
function store(): Store {
    static $store = null;
    if ($store instanceof Store) {
        return $store;
    }

    $cfg = config();
    $m = $cfg['mysql'] ?? [];
    $configured = trim((string)($m['username'] ?? '')) !== ''
        && trim((string)($m['database'] ?? '')) !== '';

    if ($configured && extension_loaded('pdo_mysql')) {
        try {
            $store = new MySqlStore($m);
            return $store;
        } catch (Throwable $e) {
            error_log('Popcorn Silo: MySQL unavailable, using JSON store — ' . $e->getMessage());
        }
    }

    $store = new JsonStore($cfg['data_dir']);
    return $store;
}
