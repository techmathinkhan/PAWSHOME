<?php
/** PawsHome — Surrender API */
require_once __DIR__ . '/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id'])   ? (int)$_GET['id'] : null;
$mine   = !empty($_GET['mine']);

if ($method === 'POST') {
    $d          = body();
    $ownerName  = trim($d['owner_name']  ?? '');
    $ownerEmail = trim($d['owner_email'] ?? '');
    $petName    = trim($d['pet_name']    ?? '');
    $species    = trim($d['species']     ?? '');
    $reason     = trim($d['reason']      ?? '');

    if (!$ownerName || !$ownerEmail || !$petName || !$species || !$reason)
        jsonError('Owner name, email, pet name, species and reason are required');
    if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL))
        jsonError('Invalid email address');

    $imgUrl = saveUploadedImage('image');
    $user   = getCurrentUser();
    $vacc   = in_array(strtolower($d['vaccinated'] ?? 'false'), ['true','1','on']) ? 1 : 0;

    $db = getDB();
    $db->prepare(
        'INSERT INTO surrenders
         (owner_name,owner_email,owner_phone,pet_name,species,breed,age,gender,
          vaccinated,reason,health_info,image_url,status,user_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,"under_review",?)'
    )->execute([
        $ownerName, $ownerEmail,
        trim($d['owner_phone'] ?? ''),
        $petName, $species,
        trim($d['breed']       ?? ''),
        (float)($d['age']      ?? 0),
        $d['gender']           ?? 'Unknown',
        $vacc, $reason,
        trim($d['health_info'] ?? ''),
        $imgUrl,
        $user ? $user['id'] : null,
    ]);

    $newId = (int)$db->lastInsertId();
    $s     = $db->prepare('SELECT * FROM surrenders WHERE id = ?');
    $s->execute([$newId]);
    jsonOK($s->fetch(), 201);
}

if ($method === 'GET' && !$id) {
    $db = getDB();
    if ($mine) {
        $user = requireAuth();
        $s    = $db->prepare('SELECT * FROM surrenders WHERE user_id = ? ORDER BY created_at DESC');
        $s->execute([$user['id']]);
    } else {
        requireAdmin();
        $s = $db->query('SELECT * FROM surrenders ORDER BY created_at DESC');
    }
    jsonOK($s->fetchAll());
}

if ($method === 'PUT' && $id) {
    requireAdmin();
    $d      = body();
    $db     = getDB();
    $sets   = []; $params = [];
    if (!empty($d['status']))     { $sets[] = 'status = ?';      $params[] = $d['status']; }
    if (isset($d['admin_notes'])) { $sets[] = 'admin_notes = ?'; $params[] = $d['admin_notes']; }
    if ($sets) {
        $params[] = $id;
        $db->prepare('UPDATE surrenders SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }
    $s = $db->prepare('SELECT * FROM surrenders WHERE id = ?');
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) jsonError('Not found', 404);
    jsonOK($row);
}

jsonError('Method not allowed', 405);
