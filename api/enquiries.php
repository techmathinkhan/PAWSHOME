<?php
/**
 * PawsHome — Enquiries API
 * POST              → submit (public)
 * GET               → list all (admin)
 * GET  ?mine=1      → my enquiries (user)
 * PUT  ?id=X        → update status (admin)
 * DELETE ?id=X      → delete (admin)
 */
require_once __DIR__ . '/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id'])   ? (int)$_GET['id'] : null;
$mine   = !empty($_GET['mine']);

if ($method === 'POST') {
    $d       = body();
    $name    = trim($d['user_name'] ?? '');
    $email   = trim($d['email']     ?? '');
    $message = trim($d['message']   ?? '');
    $petId   = !empty($d['pet_id']) ? (int)$d['pet_id'] : null;

    if (!$name || !$email || !$message || !$petId)
        jsonError('Name, email, pet and message are required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonError('Invalid email address');

    $user = getCurrentUser();
    $db   = getDB();
    $db->prepare(
        'INSERT INTO enquiries (user_name,email,phone,pet_id,message,status,user_id)
         VALUES (?,?,?,?,?,"pending",?)'
    )->execute([$name, $email, trim($d['phone'] ?? ''), $petId, $message, $user ? $user['id'] : null]);

    $newId = (int)$db->lastInsertId();
    $s     = $db->prepare(
        'SELECT e.*, p.name AS pet_name FROM enquiries e
         LEFT JOIN pets p ON p.id = e.pet_id WHERE e.id = ?'
    );
    $s->execute([$newId]);
    jsonOK($s->fetch(), 201);
}

if ($method === 'GET' && !$id) {
    $db = getDB();
    if ($mine) {
        $user = requireAuth();
        $s    = $db->prepare(
            'SELECT e.*, p.name AS pet_name FROM enquiries e
             LEFT JOIN pets p ON p.id = e.pet_id
             WHERE e.user_id = ? ORDER BY e.created_at DESC'
        );
        $s->execute([$user['id']]);
    } else {
        requireAdmin();
        $s = $db->query(
            'SELECT e.*, p.name AS pet_name FROM enquiries e
             LEFT JOIN pets p ON p.id = e.pet_id ORDER BY e.created_at DESC'
        );
    }
    jsonOK($s->fetchAll());
}

if ($method === 'PUT' && $id) {
    requireAdmin();
    $d  = body();
    $db = getDB();
    if (!empty($d['status'])) {
        $db->prepare('UPDATE enquiries SET status = ? WHERE id = ?')->execute([$d['status'], $id]);
    }
    $s = $db->prepare(
        'SELECT e.*, p.name AS pet_name FROM enquiries e
         LEFT JOIN pets p ON p.id = e.pet_id WHERE e.id = ?'
    );
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) jsonError('Not found', 404);
    jsonOK($row);
}

if ($method === 'DELETE' && $id) {
    requireAdmin();
    getDB()->prepare('DELETE FROM enquiries WHERE id = ?')->execute([$id]);
    jsonOK(['ok' => true]);
}

jsonError('Method not allowed', 405);
