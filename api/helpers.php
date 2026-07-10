<?php
/**
 * PawsHome — Shared helper functions
 * Included by every API file.
 */
require_once __DIR__ . '/../config/database.php';

/* ── CORS & CONTENT-TYPE ─────────────────────────────────────── */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * PHP NEVER populates $_FILES or $_POST for real PUT requests — only POST.
 * So image-upload edits (e.g. "change pet photo") must be sent as POST with
 * a hidden _method=PUT field, and we rewrite REQUEST_METHOD here before any
 * route file reads it. Plain PUT (no file, JSON body) still works as before.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_method'])) {
    $_SERVER['REQUEST_METHOD'] = strtoupper($_POST['_method']);
}

/* ── JSON RESPONSES ──────────────────────────────────────────── */
function jsonOK($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

/* ── REQUEST BODY ────────────────────────────────────────────── */
function body(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $dec = json_decode($raw, true);
        return is_array($dec) ? $dec : [];
    }
    // multipart/form-data or application/x-www-form-urlencoded
    return $_POST ?: [];
}

/* ── AUTH TOKEN ──────────────────────────────────────────────── */
function getBearerToken(): ?string {
    // Apache may expose X-Auth-Token under several $_SERVER keys depending on
    // the PHP SAPI (mod_php vs CGI/FPM) and whether an internal redirect occurred.
    // The .htaccess RewriteRule [E=HTTP_X_AUTH_TOKEN:...] ensures the first key
    // is always populated, but we check all variants for maximum compatibility.
    foreach ([
        'HTTP_X_AUTH_TOKEN',            // mod_php, or after RewriteRule [E=...]
        'REDIRECT_HTTP_X_AUTH_TOKEN',   // after one internal redirect
    ] as $key) {
        if (!empty($_SERVER[$key])) return $_SERVER[$key];
    }
    // Fallback: ?token= query param (useful for environments where headers are stripped)
    return $_GET['token'] ?? null;
}

function getCurrentUser(): ?array {
    $token = getBearerToken();
    if (!$token) return null;

    $db   = getDB();
    $stmt = $db->prepare('SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW()');
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    if (!$session) return null;

    $stmt2 = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt2->execute([$session['user_id']]);
    return $stmt2->fetch() ?: null;
}

function requireAuth(): array {
    $user = getCurrentUser();
    if (!$user) jsonError('Unauthorized — please log in', 401);
    return $user;
}

function requireAdmin(): array {
    $user = requireAuth();
    if ($user['role'] !== 'admin') jsonError('Admin access required', 403);
    return $user;
}

/* ── IMAGE UPLOAD ────────────────────────────────────────────── */
// UPLOAD_DIR is absolute path on disk; UPLOAD_URL is what the browser fetches
// Both resolve relative to THIS file's location (api/ → parent dir → uploads/)
define('UPLOAD_DIR', realpath(__DIR__ . '/..') . '/uploads/');
// UPLOAD_URL uses the BASE_PATH set in index.html via the <base> tag
// so we just store a relative path that works from the project root
define('UPLOAD_URL_PREFIX', 'uploads/');

function saveUploadedImage(string $field = 'image'): ?string {
    if (empty($_FILES[$field]['tmp_name'])) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK)       return null;
    if ($file['size'] > 16 * 1024 * 1024)       return null; // 16 MB limit

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return null;

    // MIME check
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'])) return null;

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) return null;

    return UPLOAD_URL_PREFIX . $filename;   // e.g. "uploads/abc123.jpg"
}

/* ── DATA FORMATTERS ─────────────────────────────────────────── */
function userDict(array $u): array {
    $favs = $u['favorites'] ?? '[]';
    if (is_string($favs)) $favs = json_decode($favs, true) ?: [];
    return [
        'id'         => (int)$u['id'],
        'name'       => $u['name'],
        'email'      => $u['email'],
        'role'       => $u['role'],
        'phone'      => $u['phone'] ?? '',
        'favorites'  => $favs,
        'created_at' => $u['created_at'],
    ];
}

function petDict(array $p): array {
    return [
        'id'              => (int)$p['id'],
        'name'            => $p['name'],
        'species'         => $p['species'],
        'breed'           => $p['breed'],
        'age'             => (float)$p['age'],
        'gender'          => $p['gender'],
        'description'     => $p['description'],
        'vaccinated'      => (bool)$p['vaccinated'],
        'status'          => $p['status'],
        'image_url'       => $p['image_url'],
        'color'           => $p['color']           ?? '',
        'weight'          => $p['weight']           ?? '',
        'health_notes'    => $p['health_notes']    ?? '',
        'adopter_name'    => $p['adopter_name']    ?? '',
        'adopter_message' => $p['adopter_message'] ?? '',
        'adopted_date'    => $p['adopted_date'],
        'created_at'      => $p['created_at'],
    ];
}

/* ── SESSION CREATE ──────────────────────────────────────────── */
function createSession(int $userId): string {
    $token = bin2hex(random_bytes(32)); // 64-char hex token
    $db    = getDB();
    $db->prepare(
        'INSERT INTO sessions (token, user_id, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))'
    )->execute([$token, $userId]);
    return $token;
}
