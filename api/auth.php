<?php
/**
 * PawsHome — Auth API
 * Routed via .htaccess: /api/auth/{action} → auth.php?action={action}
 */
require_once __DIR__ . '/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');

switch ("$method:$action") {

    /* POST /api/auth/register */
    case 'POST:register':
        $d    = body();
        $name = trim($d['name']     ?? '');
        $email= trim($d['email']    ?? '');
        $pass = $d['password']       ?? '';
        $phone= trim($d['phone']    ?? '');

        if (!$name || !$email || !$pass)
            jsonError('Name, email and password are required');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            jsonError('Invalid email address');
        if (strlen($pass) < 6)
            jsonError('Password must be at least 6 characters');

        $db   = getDB();
        $chk  = $db->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) jsonError('Email already registered', 409);

        $db->prepare(
            'INSERT INTO users (name, email, password_hash, phone, role, favorites)
             VALUES (?, ?, ?, ?, "user", "[]")'
        )->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT), $phone]);

        $uid   = (int)$db->lastInsertId();
        $token = createSession($uid);

        $row   = $db->prepare('SELECT * FROM users WHERE id = ?');
        $row->execute([$uid]);
        jsonOK(['token' => $token, 'user' => userDict($row->fetch())], 201);
        break;

    /* POST /api/auth/login */
    case 'POST:login':
        $d    = body();
        $email= trim($d['email']    ?? '');
        $pass = $d['password']       ?? '';

        if (!$email || !$pass) jsonError('Email and password required');

        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password_hash']))
            jsonError('Invalid email or password', 401);

        $token = createSession((int)$user['id']);
        jsonOK(['token' => $token, 'user' => userDict($user)]);
        break;

    /* POST /api/auth/logout */
    case 'POST:logout':
        $token = getBearerToken();
        if ($token) {
            getDB()->prepare('DELETE FROM sessions WHERE token = ?')->execute([$token]);
        }
        jsonOK(['ok' => true]);
        break;

    /* GET /api/auth/me */
    case 'GET:me':
        $user = requireAuth();
        jsonOK(userDict($user));
        break;

    default:
        jsonError('Endpoint not found', 404);
}
