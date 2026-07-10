<?php
/**
 * PawsHome — Pets API
 * GET    ?              → list with optional filters
 * GET    ?id=X          → single pet
 * POST   (no id)        → create (admin, multipart)
 * POST   ?id=X&action=favorite → toggle fav (user)
 * PUT    ?id=X          → update (admin, multipart)
 * DELETE ?id=X          → delete (admin)
 */
require_once __DIR__ . '/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
// Fallback: when _method override is used (POST→PUT for multipart uploads),
// the id may also be passed in the form body as _id if URL rewriting is unreliable
if (!$id && !empty($_POST['_id'])) $id = (int)$_POST['_id'];
$action = $_GET['action'] ?? '';

/* ── LIST / SINGLE ─────────────────────────────────────────── */
if ($method === 'GET') {
    $db = getDB();

    if ($id) {
        $s = $db->prepare('SELECT * FROM pets WHERE id = ?');
        $s->execute([$id]);
        $pet = $s->fetch();
        if (!$pet) jsonError('Pet not found', 404);
        jsonOK(petDict($pet));
    }

    $where = []; $params = [];

    if (!empty($_GET['status']))    { $where[] = 'status = ?';          $params[] = $_GET['status']; }
    if (!empty($_GET['species']))   { $where[] = 'species = ?';         $params[] = $_GET['species']; }
    if (!empty($_GET['gender']))    { $where[] = 'gender = ?';          $params[] = $_GET['gender']; }

    if (isset($_GET['vaccinated']) && $_GET['vaccinated'] !== '') {
        $where[]  = 'vaccinated = ?';
        $params[] = $_GET['vaccinated'] === 'true' ? 1 : 0;
    }
    if (!empty($_GET['search'])) {
        $like     = '%' . $_GET['search'] . '%';
        $where[]  = '(name LIKE ? OR breed LIKE ?)';
        $params[] = $like; $params[] = $like;
    }
    switch ($_GET['age_range'] ?? '') {
        case 'puppy':  $where[] = 'age < 1';                break;
        case 'young':  $where[] = 'age >= 1 AND age < 3';   break;
        case 'adult':  $where[] = 'age >= 3 AND age < 7';   break;
        case 'senior': $where[] = 'age >= 7';               break;
    }

    $sql  = 'SELECT * FROM pets';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY created_at DESC';

    $s = $db->prepare($sql);
    $s->execute($params);
    jsonOK(array_map('petDict', $s->fetchAll()));
}

/* ── TOGGLE FAVOURITE ──────────────────────────────────────── */
if ($method === 'POST' && $id && $action === 'favorite') {
    $user = requireAuth();
    $db   = getDB();

    $favs = $user['favorites'];
    if (is_string($favs)) $favs = json_decode($favs, true) ?: [];
    $pos  = array_search($id, $favs);

    if ($pos !== false) { array_splice($favs, $pos, 1); $act = 'removed'; }
    else                { $favs[] = $id;                 $act = 'added'; }

    $db->prepare('UPDATE users SET favorites = ? WHERE id = ?')
       ->execute([json_encode(array_values($favs)), $user['id']]);

    jsonOK(['action' => $act, 'favorites' => array_values($favs)]);
}

/* ── CREATE ────────────────────────────────────────────────── */
if ($method === 'POST' && !$id) {
    requireAdmin();
    $imgUrl = saveUploadedImage('image');
    $d      = body();

    $vacc   = in_array(strtolower($d['vaccinated'] ?? 'false'), ['true','1','on']) ? 1 : 0;
    $aDate  = !empty($d['adopted_date']) ? $d['adopted_date'] : null;
    if (($d['status'] ?? 'available') === 'adopted' && !$aDate)
        $aDate = date('Y-m-d');

    $db = getDB();
    $db->prepare(
        'INSERT INTO pets
         (name,species,breed,age,gender,description,vaccinated,status,
          image_url,color,weight,health_notes,adopter_name,adopter_message,adopted_date)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $d['name']            ?? '',
        $d['species']         ?? '',
        $d['breed']           ?? '',
        (float)($d['age']     ?? 0),
        $d['gender']          ?? 'Male',
        $d['description']     ?? '',
        $vacc,
        $d['status']          ?? 'available',
        $imgUrl,
        $d['color']           ?? '',
        $d['weight']          ?? '',
        $d['health_notes']    ?? '',
        $d['adopter_name']    ?? '',
        $d['adopter_message'] ?? '',
        $aDate,
    ]);

    $newId = (int)$db->lastInsertId();
    $s     = $db->prepare('SELECT * FROM pets WHERE id = ?');
    $s->execute([$newId]);
    jsonOK(petDict($s->fetch()), 201);
}

/* ── UPDATE ────────────────────────────────────────────────── */
if ($method === 'PUT' && $id) {
    requireAdmin();
    $db = getDB();

    // Check pet exists
    $chk = $db->prepare('SELECT id, adopted_date FROM pets WHERE id = ?');
    $chk->execute([$id]);
    $existing = $chk->fetch();
    if (!$existing) jsonError('Pet not found', 404);

    $newImg = saveUploadedImage('image');
    $d      = body();
    $sets   = []; $params = [];

    foreach (['name','species','breed','gender','description','status',
              'color','weight','health_notes','adopter_name','adopter_message'] as $f) {
        if (array_key_exists($f, $d)) { $sets[] = "$f = ?"; $params[] = $d[$f]; }
    }
    if (array_key_exists('age', $d))        { $sets[] = 'age = ?';        $params[] = (float)$d['age']; }
    if (array_key_exists('vaccinated', $d)) {
        $sets[]   = 'vaccinated = ?';
        $params[] = in_array(strtolower($d['vaccinated']), ['true','1','on']) ? 1 : 0;
    }
    if ($newImg)                            { $sets[] = 'image_url = ?';   $params[] = $newImg; }

    // Handle adopted_date
    $newStatus = $d['status'] ?? null;
    if ($newStatus === 'adopted') {
        $aDate = !empty($d['adopted_date']) ? $d['adopted_date'] : ($existing['adopted_date'] ?: date('Y-m-d'));
        $sets[]   = 'adopted_date = ?';
        $params[] = $aDate;
    } elseif ($newStatus !== null) {
        $sets[]   = 'adopted_date = ?';
        $params[] = null;
    }

    if ($sets) {
        $params[] = $id;
        $db->prepare('UPDATE pets SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    $s = $db->prepare('SELECT * FROM pets WHERE id = ?');
    $s->execute([$id]);
    jsonOK(petDict($s->fetch()));
}

/* ── DELETE ────────────────────────────────────────────────── */
if ($method === 'DELETE' && $id) {
    requireAdmin();
    $db = getDB();
    $s  = $db->prepare('SELECT id FROM pets WHERE id = ?');
    $s->execute([$id]);
    if (!$s->fetch()) jsonError('Pet not found', 404);
    $db->prepare('DELETE FROM pets WHERE id = ?')->execute([$id]);
    jsonOK(['ok' => true]);
}

jsonError('Method not allowed', 405);
