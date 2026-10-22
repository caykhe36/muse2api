<?php
// Tự động chuyển hướng trang chủ vào Dashboard
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if ($uri === '/' || $uri === '' || $uri === '/index.php') {
    header('Location: /dashboard');
    exit;
}

// Nếu người dùng gõ thẳng /admin hoặc /admin/accounts trên trình duyệt mà không có header auth
$is_browser = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false;
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($is_browser && empty($auth_header) && strpos($uri, '/admin') === 0 && strpos($uri, '/dashboard') !== 0) {
    header('Location: /dashboard#accounts');
    exit;
}

$backend = 'http://127.0.0.1:18610';
$url = $backend . $uri;

// Forward headers
$headers = [];
$incoming_headers = function_exists('getallheaders') ? getallheaders() : [];
$has_auth = false;

foreach ($incoming_headers as $name => $value) {
    if (strtolower($name) === 'authorization') {
        $has_auth = true;
    }
    if (strtolower($name) !== 'host') {
        $headers[] = "$name: $value";
    }
}

// Nếu Apache/FastCGI lọc mất Authorization, lấy lại từ $_SERVER
if (!$has_auth) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    if ($auth) {
        $headers[] = "Authorization: $auth";
    }
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);

if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
    $input = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
}

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => [
            'message' => 'muse2api Python backend is not running on port 18610.',
            'type' => 'gateway_error',
            'code' => 'service_unavailable'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$response_headers = substr($response, 0, $header_size);
$response_body = substr($response, $header_size);

http_response_code($http_code);
foreach (explode("\r\n", $response_headers) as $hdr) {
    if ($hdr && stripos($hdr, 'Transfer-Encoding:') === false) {
        header($hdr);
    }
}

echo $response_body;
