<?php
/**
 * api/config.php
 * Loads .env, then falls back to LOCAL_* constants, then hard-coded defaults.
 */

// ---------- Load .env into environment (if present) ----------
$__envFile = __DIR__ . '/../.env';
if (is_readable($__envFile)) {
    $__lines = file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($__lines as $__line) {
        $__line = trim($__line);
        if ($__line === '' || $__line[0] === '#') continue;
        if (strpos($__line, '=') === false) continue;

        list($__k, $__v) = explode('=', $__line, 2);
        $__k = trim($__k);
        $__v = trim($__v);

        // Strip surrounding quotes
        if (strlen($__v) >= 2) {
            $__f = $__v[0];
            $__l = substr($__v, -1);
            if (($__f === '"' && $__l === '"') || ($__f === "'" && $__l === "'")) {
                $__v = substr($__v, 1, -1);
            }
        }

        // Real env vars win — only set if not already present
        if (getenv($__k) === false && !isset($_ENV[$__k]) && !isset($_SERVER[$__k])) {
            putenv("$__k=$__v");
            $_ENV[$__k]    = $__v;
            $_SERVER[$__k] = $__v;
        }
    }
    unset($__envFile, $__lines, $__line, $__k, $__v, $__f, $__l);
}

// ---------- Optional local overrides ----------
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ---------- Resolve config values ----------
// Environment variables take priority; otherwise use local/default values.
define('DB_HOST', getenv('SWS_DB_HOST') ?: (defined('LOCAL_DB_HOST') ? LOCAL_DB_HOST : 'localhost'));
define('DB_USER', getenv('SWS_DB_USER') ?: (defined('LOCAL_DB_USER') ? LOCAL_DB_USER : 'root'));
define('DB_PASS', getenv('SWS_DB_PASS') !== false ? getenv('SWS_DB_PASS') : (defined('LOCAL_DB_PASS') ? LOCAL_DB_PASS : ''));
define('DB_NAME', getenv('SWS_DB_NAME') ?: (defined('LOCAL_DB_NAME') ? LOCAL_DB_NAME : 'sws_db'));

define('SMS_API_URL', getenv('SWS_SMS_API_URL') ?: (defined('LOCAL_SMS_API_URL') ? LOCAL_SMS_API_URL : 'https://api.semaphore.co/api/v4/messages'));
define('SMS_API_KEY', getenv('SWS_SMS_API_KEY') ?: (defined('LOCAL_SMS_API_KEY') ? LOCAL_SMS_API_KEY : ''));
define('SMS_SENDER_NAME', getenv('SWS_SMS_SENDER_NAME') ?: (defined('LOCAL_SMS_SENDER_NAME') ? LOCAL_SMS_SENDER_NAME : 'SWS'));

// -------- Connect --------
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'message' => 'Database connection failed. Check .env and confirm MySQL is running.']));
}
$conn->set_charset('utf8mb4');

// -------- Headers --------
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit(0);
}

// -------- Helpers --------
function body() {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : $_POST;
}

function respond($ok, $message, $extra = array()) {
    echo json_encode(array_merge(array('success' => $ok, 'message' => $message), $extra));
    exit;
}

function requireRole($conn, $user_id, $roles = array()) {
    $user_id = intval($user_id);
    if ($user_id <= 0) respond(false, 'Not authenticated.');

    $stmt = $conn->prepare("SELECT id, full_name, email, phone, address, role FROM users WHERE id=? AND is_active=1");
    if (!$stmt) respond(false, 'Prepare error: ' . $conn->error);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$u) respond(false, 'User not found or inactive.');
    if (count($roles) > 0 && !in_array($u['role'], $roles, true)) {
        respond(false, 'Access denied.');
    }
    return $u;
}

function logActivity($conn, $user_id, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?,?,?)");
    if (!$stmt) return;
    $stmt->bind_param('iss', $user_id, $action, $details);
    $stmt->execute();
    $stmt->close();
}

function safeStr($s, $max = 200) {
    return function_exists('mb_substr') ? mb_substr(trim((string)$s), 0, $max) : substr(trim((string)$s), 0, $max);
}