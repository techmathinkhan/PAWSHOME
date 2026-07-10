<?php
/** PawsHome — Blog API */
require_once __DIR__ . '/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($method === 'GET') {
    $db = getDB();
    if ($id) {
        $s = $db->prepare('SELECT * FROM blog_posts WHERE id = ?');
        $s->execute([$id]);
        $row = $s->fetch();
        if (!$row) jsonError('Post not found', 404);
        jsonOK($row);
    }
    jsonOK($db->query('SELECT * FROM blog_posts ORDER BY created_at DESC')->fetchAll());
}

if ($method === 'POST') {
    $admin   = requireAdmin();
    $d       = body();
    $title   = trim($d['title']   ?? '');
    $summary = trim($d['summary'] ?? '');
    if (!$title || !$summary) jsonError('Title and summary are required');

    $db = getDB();
    $db->prepare(
        'INSERT INTO blog_posts (title,category,summary,content,emoji,author)
         VALUES (?,?,?,?,?,?)'
    )->execute([
        $title,
        $d['category'] ?? 'Care Tips',
        $summary,
        trim($d['content'] ?? ''),
        $d['emoji']    ?? '🐾',
        $admin['name'],
    ]);

    $newId = (int)$db->lastInsertId();
    $s     = $db->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $s->execute([$newId]);
    jsonOK($s->fetch(), 201);
}

if ($method === 'DELETE' && $id) {
    requireAdmin();
    getDB()->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
    jsonOK(['ok' => true]);
}

jsonError('Method not allowed', 405);
