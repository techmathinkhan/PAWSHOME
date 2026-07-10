<?php
/**
 * PawsHome — Adoption Applications API (Full Workflow)
 *
 * POST  /api/applications            submit application (auth user)
 * GET   /api/applications            list all (admin)
 * GET   /api/applications?mine=1     user's own applications
 * GET   /api/applications?id=X       single application
 * PUT   /api/applications?id=X       update status / notes (admin)
 * DELETE /api/applications?id=X      delete (admin)
 *
 * Status workflow:
 *   submitted → under_review → interview_scheduled → approved → rejected
 *   When approved → pet status auto-changes to adopted
 */
require_once __DIR__ . '/helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id'])   ? (int)$_GET['id']   : null;
$mine   = !empty($_GET['mine']);

/* ── CREATE ─────────────────────────────────────────────────── */
if ($method === 'POST' && !$id) {
    $user = requireAuth();
    $d    = body();

    $petId    = !empty($d['pet_id'])  ? (int)$d['pet_id']  : null;
    $fullName = trim($d['full_name']  ?? '');
    $email    = trim($d['email']      ?? $user['email']);
    $phone    = trim($d['phone']      ?? '');
    $address  = trim($d['address']    ?? '');
    $reason   = trim($d['reason']     ?? '');

    if (!$petId || !$fullName || !$reason)
        jsonError('Pet, full name and reason for adoption are required');

    $db = getDB();

    // Prevent duplicate pending application for same pet
    $dup = $db->prepare(
        "SELECT id FROM applications
         WHERE user_id = ? AND pet_id = ?
           AND status NOT IN ('rejected','withdrawn')"
    );
    $dup->execute([$user['id'], $petId]);
    if ($dup->fetch()) jsonError('You already have an active application for this pet', 409);

    $db->prepare(
        'INSERT INTO applications
         (user_id, pet_id, full_name, email, phone, address,
          reason, living_situation, has_children, has_other_pets,
          experience, status, admin_notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,"submitted","")'
    )->execute([
        $user['id'], $petId,
        $fullName, $email, $phone, $address, $reason,
        trim($d['living_situation']  ?? ''),
        (int)(!empty($d['has_children'])),
        (int)(!empty($d['has_other_pets'])),
        trim($d['experience']        ?? ''),
    ]);

    $newId = (int)$db->lastInsertId();
    $s     = $db->prepare(
        'SELECT a.*, p.name AS pet_name, p.species AS pet_species,
                p.breed AS pet_breed, p.image_url AS pet_image
         FROM applications a
         LEFT JOIN pets p ON p.id = a.pet_id
         WHERE a.id = ?'
    );
    $s->execute([$newId]);
    jsonOK($s->fetch(), 201);
}

/* ── LIST ───────────────────────────────────────────────────── */
if ($method === 'GET' && !$id) {
    $db = getDB();

    if ($mine) {
        $user = requireAuth();
        $s    = $db->prepare(
            'SELECT a.*, p.name AS pet_name, p.species AS pet_species,
                    p.breed AS pet_breed, p.image_url AS pet_image
             FROM applications a
             LEFT JOIN pets p ON p.id = a.pet_id
             WHERE a.user_id = ?
             ORDER BY a.created_at DESC'
        );
        $s->execute([$user['id']]);
    } else {
        requireAdmin();
        $status = $_GET['status'] ?? '';
        if ($status) {
            $s = $db->prepare(
                'SELECT a.*, p.name AS pet_name, p.species AS pet_species,
                        p.breed AS pet_breed, p.image_url AS pet_image,
                        u.name AS applicant_name
                 FROM applications a
                 LEFT JOIN pets p ON p.id = a.pet_id
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.status = ?
                 ORDER BY a.created_at DESC'
            );
            $s->execute([$status]);
        } else {
            $s = $db->query(
                'SELECT a.*, p.name AS pet_name, p.species AS pet_species,
                        p.breed AS pet_breed, p.image_url AS pet_image,
                        u.name AS applicant_name
                 FROM applications a
                 LEFT JOIN pets p ON p.id = a.pet_id
                 LEFT JOIN users u ON u.id = a.user_id
                 ORDER BY a.created_at DESC'
            );
        }
    }
    jsonOK($s->fetchAll());
}

/* ── SINGLE ──────────────────────────────────────────────────── */
if ($method === 'GET' && $id) {
    $user = requireAuth();
    $db   = getDB();
    $s    = $db->prepare(
        'SELECT a.*, p.name AS pet_name, p.species AS pet_species,
                p.breed AS pet_breed, p.image_url AS pet_image,
                u.name AS applicant_name, u.email AS applicant_email
         FROM applications a
         LEFT JOIN pets p ON p.id = a.pet_id
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.id = ?'
    );
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) jsonError('Application not found', 404);
    // Only admin or owner can see it
    if ($user['role'] !== 'admin' && (int)$row['user_id'] !== (int)$user['id'])
        jsonError('Access denied', 403);
    jsonOK($row);
}

/* ── UPDATE (admin) ─────────────────────────────────────────── */
if ($method === 'PUT' && $id) {
    requireAdmin();
    $d  = body();
    $db = getDB();

    $s = $db->prepare('SELECT * FROM applications WHERE id = ?');
    $s->execute([$id]);
    $app = $s->fetch();
    if (!$app) jsonError('Application not found', 404);

    $sets   = [];
    $params = [];
    $newStatus = $d['status'] ?? null;

    if ($newStatus) {
        $allowed = ['submitted','under_review','interview_scheduled','approved','rejected','withdrawn'];
        if (!in_array($newStatus, $allowed))
            jsonError('Invalid status value');
        $sets[]   = 'status = ?';
        $params[] = $newStatus;

        // Auto-set interview date when scheduling
        if ($newStatus === 'interview_scheduled' && !empty($d['interview_date'])) {
            $sets[]   = 'interview_date = ?';
            $params[] = $d['interview_date'];
        }
        // Auto-set approval date
        if ($newStatus === 'approved') {
            $sets[]   = 'approved_at = ?';
            $params[] = date('Y-m-d H:i:s');

            // Mark pet as adopted
            $db->prepare(
                "UPDATE pets SET status='adopted', adopted_date=CURDATE(),
                  adopter_name=?, adopter_message=?
                 WHERE id = ?"
            )->execute([
                $app['full_name'],
                $d['adopter_message'] ?? '',
                $app['pet_id'],
            ]);
        }
    }
    if (isset($d['admin_notes'])) {
        $sets[]   = 'admin_notes = ?';
        $params[] = $d['admin_notes'];
    }
    if (isset($d['interview_date'])) {
        $sets[]   = 'interview_date = ?';
        $params[] = $d['interview_date'];
    }

    if ($sets) {
        $params[] = $id;
        $db->prepare('UPDATE applications SET ' . implode(', ', $sets) . ' WHERE id = ?')
           ->execute($params);
    }

    $s2 = $db->prepare(
        'SELECT a.*, p.name AS pet_name, p.image_url AS pet_image,
                u.name AS applicant_name
         FROM applications a
         LEFT JOIN pets p ON p.id = a.pet_id
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.id = ?'
    );
    $s2->execute([$id]);
    jsonOK($s2->fetch());
}

/* ── DELETE ─────────────────────────────────────────────────── */
if ($method === 'DELETE' && $id) {
    requireAdmin();
    getDB()->prepare('DELETE FROM applications WHERE id = ?')->execute([$id]);
    jsonOK(['ok' => true]);
}

jsonError('Method not allowed', 405);
