<?php
/**
 * PawsHome — Adoption Success Stories API
 *
 * POST   /api/stories            submit story (auth user)
 * GET    /api/stories            list approved stories (public)
 * GET    /api/stories?all=1      list all incl. pending (admin)
 * GET    /api/stories?id=X       single story
 * PUT    /api/stories?id=X       approve / update (admin)
 * DELETE /api/stories?id=X       delete (admin or owner)
 */
require_once __DIR__ . '/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$all    = !empty($_GET['all']);

/* ── CREATE ─────────────────────────────────────────────────── */
if ($method === 'POST' && !$id) {
    $user   = requireAuth();
    $imgUrl = saveUploadedImage('image');
    $d      = body();

    $petName  = trim($d['pet_name']  ?? '');
    $title    = trim($d['title']     ?? '');
    $story    = trim($d['story']     ?? '');

    if (!$petName || !$title || !$story)
        jsonError('Pet name, title and story are required');

    $db = getDB();
    $db->prepare(
        'INSERT INTO success_stories
         (user_id, pet_name, pet_species, adopter_name, title, story,
          image_url, status, adopted_year)
         VALUES (?,?,?,?,?,?,?,"pending",?)'
    )->execute([
        $user['id'],
        $petName,
        trim($d['pet_species']   ?? ''),
        trim($d['adopter_name']  ?? $user['name']),
        $title, $story, $imgUrl,
        !empty($d['adopted_year']) ? (int)$d['adopted_year'] : (int)date('Y'),
    ]);

    $newId = (int)$db->lastInsertId();
    $s     = $db->prepare(
        'SELECT ss.*, u.name AS submitter_name
         FROM success_stories ss
         LEFT JOIN users u ON u.id = ss.user_id
         WHERE ss.id = ?'
    );
    $s->execute([$newId]);
    jsonOK($s->fetch(), 201);
}

/* ── LIST ───────────────────────────────────────────────────── */
if ($method === 'GET' && !$id) {
    $db = getDB();
    if ($all) {
        requireAdmin();
        $s = $db->query(
            'SELECT ss.*, u.name AS submitter_name
             FROM success_stories ss
             LEFT JOIN users u ON u.id = ss.user_id
             ORDER BY ss.created_at DESC'
        );
    } else {
        // Public: approved only
        $s = $db->query(
            "SELECT ss.*, u.name AS submitter_name
             FROM success_stories ss
             LEFT JOIN users u ON u.id = ss.user_id
             WHERE ss.status = 'approved'
             ORDER BY ss.created_at DESC"
        );
    }
    jsonOK($s->fetchAll());
}

/* ── SINGLE ──────────────────────────────────────────────────── */
if ($method === 'GET' && $id) {
    $db = getDB();
    $s  = $db->prepare(
        'SELECT ss.*, u.name AS submitter_name
         FROM success_stories ss
         LEFT JOIN users u ON u.id = ss.user_id
         WHERE ss.id = ?'
    );
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) jsonError('Story not found', 404);
    jsonOK($row);
}

/* ── UPDATE ──────────────────────────────────────────────────── */
if ($method === 'PUT' && $id) {
    requireAdmin();
    $d      = body();
    $db     = getDB();
    $sets   = [];
    $params = [];

    if (array_key_exists('status', $d)) { $sets[] = 'status = ?'; $params[] = $d['status']; }
    if (array_key_exists('featured', $d)) { $sets[] = 'featured = ?'; $params[] = (int)$d['featured']; }

    if ($sets) {
        $params[] = $id;
        $db->prepare('UPDATE success_stories SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    $s = $db->prepare('SELECT * FROM success_stories WHERE id = ?');
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) jsonError('Not found', 404);
    jsonOK($row);
}

/* ── DELETE ──────────────────────────────────────────────────── */
if ($method === 'DELETE' && $id) {
    $user = requireAuth();
    $db   = getDB();
    $s    = $db->prepare('SELECT user_id FROM success_stories WHERE id = ?');
    $s->execute([$id]);
    $row  = $s->fetch();
    if (!$row) jsonError('Not found', 404);
    if ($user['role'] !== 'admin' && (int)$row['user_id'] !== (int)$user['id'])
        jsonError('Access denied', 403);
    $db->prepare('DELETE FROM success_stories WHERE id = ?')->execute([$id]);
    jsonOK(['ok' => true]);
}

jsonError('Method not allowed', 405);
