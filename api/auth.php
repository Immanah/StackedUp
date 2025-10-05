<?php
require __DIR__ . '/bootstrap.php';

$usersFile = $DATA_DIR . '/users.json';
$users = read_json($usersFile, []);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if ($method !== 'POST') { http_response_code(405); exit; }
        $body = body_json();
        require_fields($body, ['email','password','firstName','lastName']);
        $email = strtolower(trim($body['email']));
        foreach ($users as $u) {
            if (($u['email'] ?? '') === $email) {
                http_response_code(409);
                echo json_encode(['error' => 'Email already registered']);
                exit;
            }
        }
        $id = bin2hex(random_bytes(8));
        $user = [
            'id' => $id,
            'email' => $email,
            'passwordHash' => password_hash($body['password'], PASSWORD_DEFAULT),
            'firstName' => $body['firstName'],
            'lastName' => $body['lastName'],
            'createdAt' => date(DATE_ATOM)
        ];
        $users[] = $user;
        write_json($usersFile, $users);
        $_SESSION['user_id'] = $id;
        echo json_encode(['ok' => true, 'user' => ['id'=>$id,'email'=>$email,'firstName'=>$user['firstName'],'lastName'=>$user['lastName']]]);
        break;

    case 'login':
        if ($method !== 'POST') { http_response_code(405); exit; }
        $body = body_json();
        require_fields($body, ['email','password']);
        $email = strtolower(trim($body['email']));
        $found = null;
        foreach ($users as $u) {
            if (($u['email'] ?? '') === $email) { $found = $u; break; }
        }
        if (!$found || !password_verify($body['password'], $found['passwordHash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            exit;
        }
        $_SESSION['user_id'] = $found['id'];
        echo json_encode(['ok' => true, 'user' => ['id'=>$found['id'],'email'=>$found['email'],'firstName'=>$found['firstName'],'lastName'=>$found['lastName']]]);
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['ok' => true]);
        break;

    case 'me':
        $uid = current_user_id();
        if (!$uid) { echo json_encode(['user'=>null]); break; }
        $found = null;
        foreach ($users as $u) { if ($u['id'] === $uid) { $found = $u; break; } }
        if (!$found) { echo json_encode(['user'=>null]); break; }
        echo json_encode(['user' => ['id'=>$found['id'],'email'=>$found['email'],'firstName'=>$found['firstName'],'lastName'=>$found['lastName']]]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
