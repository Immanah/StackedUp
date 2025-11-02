<?php
// image_helpers.php
require_once __DIR__ . '/config.php';

/**
 * Validate uploaded file (basic)
 * returns null if ok, or string error
 */
function validate_image_upload($file) {
    // file: element of $_FILES
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return 'nofile';
    if ($file['error'] !== UPLOAD_ERR_OK) return 'Upload error code ' . $file['error'];
    // max 5MB
    if ($file['size'] > 5 * 1024 * 1024) return 'File too large (max 5MB)';
    // check MIME using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!array_key_exists($mime, $allowed)) return 'Invalid image type (allowed: jpg, png, webp)';
    return null;
}

/** Create a safe filename for storage */
function make_safe_filename($original, $ext) {
    // remove path, spaces and unsafe chars
    $name = pathinfo($original, PATHINFO_FILENAME);
    $name = preg_replace('/[^A-Za-z0-9_\-]/', '-', $name);
    $name = substr($name, 0, 80);
    $unique = bin2hex(random_bytes(6));
    return sprintf('%s-%s.%s', $name, $unique, $ext);
}

/** Create thumbnail using GD, returns thumb filename or false */
function create_thumbnail($sourcePath, $destPath, $maxWidth = 300, $maxHeight = 300) {
    $imgInfo = getimagesize($sourcePath);
    if (!$imgInfo) return false;
    [$srcW, $srcH, $type] = $imgInfo;

    switch ($type) {
        case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($sourcePath); break;
        case IMAGETYPE_PNG:  $srcImg = imagecreatefrompng($sourcePath);  break;
        case IMAGETYPE_WEBP: $srcImg = imagecreatefromwebp($sourcePath); break;
        default: return false;
    }

    // preserve aspect
    $ratio = min($maxWidth / $srcW, $maxHeight / $srcH, 1);
    $newW = (int)($srcW * $ratio);
    $newH = (int)($srcH * $ratio);

    $thumb = imagecreatetruecolor($newW, $newH);

    // handle PNG transparency
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }

    imagecopyresampled($thumb, $srcImg, 0,0,0,0, $newW, $newH, $srcW, $srcH);

    // Save thumbnail (use same format as source)
    $ok = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $ok = imagejpeg($thumb, $destPath, 85); break;
        case IMAGETYPE_PNG:  $ok = imagepng($thumb, $destPath); break;
        case IMAGETYPE_WEBP: $ok = imagewebp($thumb, $destPath, 85); break;
    }

    imagedestroy($thumb);
    imagedestroy($srcImg);
    return $ok ? $destPath : false;
}
