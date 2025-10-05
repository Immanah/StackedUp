<?php
require __DIR__ . '/bootstrap.php';

$productsFile = $DATA_DIR . '/products.json';
if (!file_exists($productsFile)) {
    $seed = [
        ['id'=>'1','title'=>'Elegant Black Evening Gown','price'=>150,'designer'=>'Versace','size'=>'M','rentalDays'=>3],
        ['id'=>'2','title'=>'Red Cocktail Dress','price'=>95,'designer'=>'Dolce & Gabbana','size'=>'S','rentalDays'=>3],
        ['id'=>'3','title'=>'Navy Blue Formal Dress','price'=>120,'designer'=>'Calvin Klein','size'=>'L','rentalDays'=>3],
        ['id'=>'4','title'=>'Pink Wedding Guest Dress','price'=>85,'designer'=>'Ted Baker','size'=>'M','rentalDays'=>3],
        ['id'=>'5','title'=>'Emerald Green Prom Dress','price'=>180,'designer'=>'Jovani','size'=>'S','rentalDays'=>3],
        ['id'=>'6','title'=>'White Evening Gown','price'=>200,'designer'=>'Chanel','size'=>'L','rentalDays'=>3]
    ];
    write_json($productsFile, $seed);
}
$products = read_json($productsFile, []);

$id = $_GET['id'] ?? null;
if ($id) {
    foreach ($products as $p) {
        if ($p['id'] === $id) { echo json_encode($p); exit; }
    }
    http_response_code(404);
    echo json_encode(['error'=>'Not found']);
    exit;
}

echo json_encode($products);
