<?php
/**
 * PawsHome — Appointments API
 */
require_once __DIR__ . '/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id'])   ? (int)$_GET['id'] : null;
$mine   = !empty($_GET['mine']);

if ($method === 'POST') {
    $d      = body();
    $name   = trim($d['visitor_name'] ?? '');
    $email  = trim($d['email']        ?? '');
    $vDate  = trim($d['visit_date']   ?? '');
    $petId  = !empty($d['pet_id']) ? (int)$d['pet_id'] : null;

    if (!$name || !$email || !$petId || !$vDate)
        jsonError('Name, email, pet and visit date are required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonError('Invalid email address');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $vDate))
        jsonError('Invalid date format — use YYYY-MM-DD');

    $user = getCurrentUser();
    $db   = getDB();
    $db->prepare(
        'INSERT INTO appointments
         (visitor_name,email,phone,pet_id,visit_date,visit_time,notes,status,user_id)
         VALUES (?,?,?,?,?,?,?,"pending",?)'
    )->execute([
        $name, $email,
        trim($d['phone']      ?? ''),
        $petId, $vDate,
        $d['visit_time']      ?? '10:00 AM',
        trim($d['notes']      ?? ''),
        $user ? $user['id'] : null,
    ]);

    $newId = (int)$db->lastInsertId();
    $s     = $db->prepare(
        'SELECT a.*, p.name AS pet_name FROM appointments a
         LEFT JOIN pets p ON p.id = a.pet_id WHERE a.id = ?'
    );
    $s->execute([$newId]);
    jsonOK($s->fetch(), 201);
}

if ($method === 'GET' && !$id) {
    $db = getDB();
    if ($mine) {
        $user = requireAuth();
        $s    = $db->prepare(
            'SELECT a.*, p.name AS pet_name FROM appointments a
             LEFT JOIN pets p ON p.id = a.pet_id
             WHERE a.user_id = ? ORDER BY a.created_at DESC'
        );
        $s->execute([$user['id']]);
    } else {
        requireAdmin();
        $s = $db->query(
            'SELECT a.*, p.name AS pet_name FROM appointments a
             LEFT JOIN pets p ON p.id = a.pet_id ORDER BY a.created_at DESC'
        );
    }
    jsonOK($s->fetchAll());
}

if ($method === 'PUT' && $id) {
    requireAdmin();
    $d  = body();
    $db = getDB();
    if (!empty($d['status'])) {
        $db->prepare('UPDATE appointments SET status = ? WHERE id = ?')->execute([$d['status'], $id]);
    }
    $s = $db->prepare(
        'SELECT a.*, p.name AS pet_name FROM appointments a
         LEFT JOIN pets p ON p.id = a.pet_id WHERE a.id = ?'
    );
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) jsonError('Not found', 404);
    jsonOK($row);
}

if ($method === 'DELETE' && $id) {
    requireAdmin();
    getDB()->prepare('DELETE FROM appointments WHERE id = ?')->execute([$id]);
    jsonOK(['ok' => true]);
}

jsonError('Method not allowed', 405);
