<?php
require __DIR__ . '/bootstrap.php';

$uid = ensure_auth();
$file = $DATA_DIR . '/wishlist_' . $uid . '.json';
$wishlist = read_json($file, []); // [{id, title, price}]

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode($wishlist);
    exit;
}

if ($method === 'POST') {
    $body = body_json();
    require_fields($body, ['id','title','price']);
    foreach ($wishlist as $w) { if ($w['id'] === $body['id']) { echo json_encode(['ok'=>true]); exit; } }
    $wishlist[] = ['id'=>$body['id'],'title'=>$body['title'],'price'=>(float)$body['price']];
    write_json($file, $wishlist);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing id']); exit; }
    $wishlist = array_values(array_filter($wishlist, fn($w) => $w['id'] !== $id));
    write_json($file, $wishlist);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(405);
