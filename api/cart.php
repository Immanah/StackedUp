<?php
require __DIR__ . '/bootstrap.php';

$uid = current_user_id();
$cartFile = $DATA_DIR . '/cart_' . ($uid ?? 'guest') . '.json';
$holdsFile = $DATA_DIR . '/holds.json';

$cart = read_json($cartFile, []);
$holds = read_json($holdsFile, []); // [{productId, userId, expiresAt}]

$method = $_SERVER['REQUEST_METHOD'];

function save_cart(string $file, array $items): void { write_json($file, $items); }
function save_holds(string $file, array $holds): void { write_json($file, $holds); }

function now_ts(): int { return time(); }

// cleanup expired holds
$holds = array_values(array_filter($holds, function($h){ return ($h['expiresAt'] ?? 0) > time(); }));
save_holds($holdsFile, $holds);

if ($method === 'GET') {
    echo json_encode(['items'=>$cart, 'now'=>now_ts()]);
    exit;
}

$body = body_json();

if ($method === 'POST') {
    ensure_auth();
    require_fields($body, ['productId','title','price','days']);
    $productId = (string)$body['productId'];

    // place or refresh hold for 30 minutes
    $expiresAt = time() + 30 * 60; // 30 min
    $updated = false;
    foreach ($holds as &$h) {
        if ($h['productId'] === $productId) {
            if ($h['userId'] !== $_SESSION['user_id']) {
                http_response_code(409);
                echo json_encode(['error'=>'Item is held by another user']);
                exit;
            }
            $h['expiresAt'] = $expiresAt;
            $updated = true;
            break;
        }
    }
    unset($h);
    if (!$updated) {
        $holds[] = ['productId'=>$productId,'userId'=>$_SESSION['user_id'],'expiresAt'=>$expiresAt];
    }

    // add to cart (single qty per rental item)
    $exists = false;
    foreach ($cart as &$item) {
        if ($item['productId'] === $productId) {
            $item['qty'] = 1; // enforce 1 for rental
            $item['holdExpiresAt'] = $expiresAt;
            $exists = true;
            break;
        }
    }
    unset($item);
    if (!$exists) {
        $cart[] = [
            'id' => bin2hex(random_bytes(6)),
            'productId' => $productId,
            'title' => $body['title'],
            'price' => (float)$body['price'],
            'days' => (int)$body['days'],
            'qty' => 1,
            'holdExpiresAt' => $expiresAt
        ];
    }

    save_cart($cartFile, $cart);
    save_holds($holdsFile, $holds);
    echo json_encode(['ok'=>true,'items'=>$cart]);
    exit;
}

if ($method === 'DELETE') {
    ensure_auth();
    $id = $_GET['id'] ?? '';
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing id']); exit; }
    $cart = array_values(array_filter($cart, fn($i) => $i['id'] !== $id));
    save_cart($cartFile, $cart);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(405);
