<?php
/** PawsHome — Admin Dashboard Stats v4 */
require_once __DIR__ . '/helpers.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Method not allowed', 405);
requireAdmin();
$db = getDB();
$n  = fn($sql) => (int)$db->query($sql)->fetchColumn();
$speciesRows = $db->query('SELECT species, COUNT(*) AS cnt FROM pets GROUP BY species')->fetchAll();
$species = [];
foreach ($speciesRows as $r) $species[$r['species']] = (int)$r['cnt'];
$appPipeline = [];
foreach (['submitted','under_review','interview_scheduled','approved','rejected'] as $st) {
    $appPipeline[$st] = $n("SELECT COUNT(*) FROM applications WHERE status='$st'");
}
jsonOK([
    'available_pets'        => $n("SELECT COUNT(*) FROM pets WHERE status='available'"),
    'adopted_pets'          => $n("SELECT COUNT(*) FROM pets WHERE status='adopted'"),
    'pending_pets'          => $n("SELECT COUNT(*) FROM pets WHERE status='pending'"),
    'total_pets'            => $n('SELECT COUNT(*) FROM pets'),
    'total_users'           => $n("SELECT COUNT(*) FROM users WHERE role='user'"),
    'total_enquiries'       => $n('SELECT COUNT(*) FROM enquiries'),
    'pending_enquiries'     => $n("SELECT COUNT(*) FROM enquiries WHERE status='pending'"),
    'total_appointments'    => $n('SELECT COUNT(*) FROM appointments'),
    'pending_appointments'  => $n("SELECT COUNT(*) FROM appointments WHERE status='pending'"),
    'total_blog_posts'      => $n('SELECT COUNT(*) FROM blog_posts'),
    'surrender_submissions' => $n('SELECT COUNT(*) FROM surrenders'),
    'pending_surrenders'    => $n("SELECT COUNT(*) FROM surrenders WHERE status='under_review'"),
    'species_breakdown'     => $species,
    'total_applications'    => $n('SELECT COUNT(*) FROM applications'),
    'pending_applications'  => $n("SELECT COUNT(*) FROM applications WHERE status IN ('submitted','under_review')"),
    'application_pipeline'  => $appPipeline,
    'lost_found_open'       => $n("SELECT COUNT(*) FROM lost_found WHERE status='open'"),
    'lost_reports'          => $n("SELECT COUNT(*) FROM lost_found WHERE type='lost'"),
    'found_reports'         => $n("SELECT COUNT(*) FROM lost_found WHERE type='found'"),
    'success_stories'       => $n("SELECT COUNT(*) FROM success_stories WHERE status='approved'"),
    'stories_pending'       => $n("SELECT COUNT(*) FROM success_stories WHERE status='pending'"),
]);
