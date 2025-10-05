<?php
// bootstrap.php - common setup for API

declare(strict_types=1);

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$DATA_DIR = dirname(__DIR__) . '/data';
if (!is_dir($DATA_DIR)) {
    mkdir($DATA_DIR, 0777, true);
}

function read_json(string $file, array $default = []): array {
    if (!file_exists($file)) return $default;
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function write_json(string $file, array $data): void {
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, $file);
}

function require_fields(array $payload, array $fields): void {
    foreach ($fields as $f) {
        if (!isset($payload[$f]) || $payload[$f] === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing field: ' . $f]);
            exit;
        }
    }
}

function body_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function current_user_id(): ?string {
    return $_SESSION['user_id'] ?? null;
}

function ensure_auth(): string {
    $uid = current_user_id();
    if (!$uid) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return $uid;
}
