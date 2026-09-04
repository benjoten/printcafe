<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    json_response(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Invalid request method'], 405);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $err_code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $err_messages = [
        1 => 'The uploaded file exceeds the maximum allowed server upload size (50MB).',
        2 => 'The uploaded file exceeds the maximum form file size.',
        3 => 'The file was only partially uploaded. Please try uploading again.',
        4 => 'No file was uploaded.',
        6 => 'Missing temporary folder on server.',
        7 => 'Failed to write file to disk on server.',
        8 => 'A PHP extension stopped the file upload.'
    ];
    $msg = $err_messages[$err_code] ?? ('File upload failed (Code: ' . $err_code . ')');
    json_response(['success' => false, 'error' => $msg], 200);
}

$file = $_FILES['file'];
$max_size = 50 * 1024 * 1024; // 50 MB

if ($file['size'] > $max_size) {
    json_response(['success' => false, 'error' => 'File size exceeds maximum limit of 50MB.'], 200);
}

$orig_name = basename($file['name']);
$ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

$allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
if (!in_array($ext, $allowed_exts)) {
    json_response(['success' => false, 'error' => 'Unsupported file format. Please upload a PDF, JPG, or PNG file.'], 400);
}

// Generate secure random file key and target path
$file_key = bin2hex(random_bytes(16));
$target_filename = $file_key . '.' . $ext;
$target_path = UPLOAD_DIR . DIRECTORY_SEPARATOR . $target_filename;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    json_response(['success' => false, 'error' => 'Failed to save uploaded file on host server.'], 500);
}

json_response([
    'success' => true,
    'file_key' => $file_key,
    'file_name' => $orig_name,
    'file_path' => $target_path,
    'file_type' => $ext,
    'file_size' => $file['size'],
    'file_size_formatted' => round($file['size'] / (1024 * 1024), 2) . ' MB'
]);
