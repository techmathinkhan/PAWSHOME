<?php
/**
 * PawsHome — Lost & Found Pets API
 *
 * POST   /api/lostfound              report lost or found pet (auth)
 * GET    /api/lostfound              list all reports (public)
 * GET    /api/lostfound?id=X         single report
 * GET    /api/lostfound?mine=1       user's reports
 * PUT    /api/lostfound?id=X         update (owner or admin)
 * DELETE /api/lostfound?id=X         delete (owner or admin)
 */
require_once __DIR__ . '/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id'])   ? (int)$_GET['id']   : null;
$mine   = !empty($_GET['mine']);

/* ── CREATE ─────────────────────────────────────────────────── */
if ($method === 'POST' && !$id) {
    $user   = requireAuth();
    $imgUrl = saveUploadedImage('image');
    $d      = body();

    $type     = $d['type']        ?? 'lost';   // 'lost' | 'found'
    $petName  = trim($d['pet_name']  ?? '');
    $species  = trim($d['species']   ?? '');
    $area     = trim($d['area']      ?? '');
    $desc     = trim($d['description'] ?? '');
    $contact  = trim($d['contact']   ?? $user['email']);

    if (!$species || !$area || !$desc)
        jsonError('Species, area and description are required');

    $db = getDB();
    $db->prepare(
        'INSERT INTO lost_found
         (user_id, type, pet_name, species, breed, color, area,
          description, contact_name, contact_phone, contact_email,
          lost_found_date, image_url, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"open")'
    )->execute([
        $user['id'], $type, $petName,
        $species,
        trim($d['breed']          ?? ''),
        trim($d['color']          ?? ''),
        $area, $desc,
        trim($d['contact_name']   ?? $user['name']),
        trim($d['contact_phone']  ?? ''),
        $contact,
        !empty($d['lost_found_date']) ? $d['lost_found_date'] : date('Y-m-d'),
        $imgUrl,
    ]);

    $newId = (int)$db->lastInsertId();
    $s     = $db->prepare(
        'SELECT lf.*, u.name AS reporter_name
         FROM lost_found lf
         LEFT JOIN users u ON u.id = lf.user_id
         WHERE lf.id = ?'
    );
    $s->execute([$newId]);
    jsonOK($s->fetch(), 201);
}

/* ── LIST ───────────────────────────────────────────────────── */
if ($method === 'GET' && !$id) {
    $db     = getDB();
    $where  = [];
    $params = [];

    if ($mine) {
        $user    = requireAuth();
        $where[] = 'lf.user_id = ?';
        $params[] = $user['id'];
    }
    if (!empty($_GET['type'])) {
        $where[]  = 'lf.type = ?';
        $params[] = $_GET['type'];
    }
    if (!empty($_GET['species'])) {
        $where[]  = 'lf.species = ?';
        $params[] = $_GET['species'];
    }
    if (!empty($_GET['status'])) {
        $where[]  = 'lf.status = ?';
        $params[] = $_GET['status'];
    }

    $sql = 'SELECT lf.*, u.name AS reporter_name
            FROM lost_found lf
            LEFT JOIN users u ON u.id = lf.user_id';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY lf.created_at DESC';

    $s = $db->prepare($sql);
    $s->execute($params);
    jsonOK($s->fetchAll());
}

/* ── SINGLE ──────────────────────────────────────────────────── */
if ($method === 'GET' && $id) {
    $db = getDB();
    $s  = $db->prepare(
        'SELECT lf.*, u.name AS reporter_name
         FROM lost_found lf
         LEFT JOIN users u ON u.id = lf.user_id
         WHERE lf.id = ?'
    );
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) jsonError('Report not found', 404);
    jsonOK($row);
}

/* ── UPDATE ──────────────────────────────────────────────────── */
if ($method === 'PUT' && $id) {
    $user   = requireAuth();
    $db     = getDB();
    $s      = $db->prepare('SELECT * FROM lost_found WHERE id = ?');
    $s->execute([$id]);
    $row    = $s->fetch();
    if (!$row) jsonError('Not found', 404);
    if ($user['role'] !== 'admin' && (int)$row['user_id'] !== (int)$user['id'])
        jsonError('Access denied', 403);

    $newImg = saveUploadedImage('image');
    $d      = body();
    $sets   = [];
    $params = [];

    foreach (['pet_name','species','breed','color','area','description',
              'contact_name','contact_phone','contact_email','status'] as $f) {
        if (array_key_exists($f, $d)) { $sets[] = "$f = ?"; $params[] = $d[$f]; }
    }
    if ($newImg) { $sets[] = 'image_url = ?'; $params[] = $newImg; }
    if (!empty($d['lost_found_date'])) { $sets[] = 'lost_found_date = ?'; $params[] = $d['lost_found_date']; }

    if ($sets) {
        $params[] = $id;
        $db->prepare('UPDATE lost_found SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    $s2 = $db->prepare('SELECT * FROM lost_found WHERE id = ?');
    $s2->execute([$id]);
    jsonOK($s2->fetch());
}

/* ── DELETE ──────────────────────────────────────────────────── */
if ($method === 'DELETE' && $id) {
    $user = requireAuth();
    $db   = getDB();
    $s    = $db->prepare('SELECT user_id FROM lost_found WHERE id = ?');
    $s->execute([$id]);
    $row  = $s->fetch();
    if (!$row) jsonError('Not found', 404);
    if ($user['role'] !== 'admin' && (int)$row['user_id'] !== (int)$user['id'])
        jsonError('Access denied', 403);
    $db->prepare('DELETE FROM lost_found WHERE id = ?')->execute([$id]);
    jsonOK(['ok' => true]);
}

jsonError('Method not allowed', 405);
