<?php
/**
 * API Live Practice — Sessions de pratique en direct
 * Version sans LiveKit, compatible WebRTC natif
 *
 * Endpoints:
 *   POST ?action=create_session   Créer une session
 *   GET  ?action=sessions         Lister les sessions disponibles
 *   POST ?action=join             Rejoindre une session
 *   POST ?action=leave            Quitter une session
 *   POST ?action=start            Démarrer (hôte uniquement)
 *   POST ?action=end              Terminer (hôte uniquement)
 *   GET  ?action=participants     Lister les participants actifs
 *   GET  ?action=detail           Détail d'une session
 */

require_once '../config/config.php';
require_once '../helpers/response.php';
require_once '../helpers/jwt.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function authenticate(): array {
    $headers    = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $m)) {
        Response::unauthorized('Token manquant');
    }
    $token = $m[1];
    if (!JWT::verify($token)) {
        Response::unauthorized('Token invalide ou expiré');
    }
    return JWT::decode($token);
}

$db     = Database::getInstance();
$payload = authenticate();
$userId  = (int) $payload['user_id'];
$action  = $_GET['action'] ?? '';

// ══════════════════════════════════════════════════════════════════════════════
// POST create_session
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_session') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (empty($data['language_id']) || empty($data['title'])) {
        Response::error('language_id et title requis', 400);
    }

    try {
        $db->beginTransaction();

        $db->execute(
            "INSERT INTO live_practice_sessions
                (host_user_id, language_id, title, description, max_participants, scheduled_at, status)
             VALUES (?, ?, ?, ?, ?, ?, 'scheduled')",
            [
                $userId,
                (int) $data['language_id'],
                trim($data['title']),
                trim($data['description'] ?? ''),
                (int) ($data['max_participants'] ?? 10),
                $data['scheduled_at'] ?? null,
            ]
        );

        $sessionId = $db->lastInsertId('live_practice_sessions_id_seq');

        // Ajouter l'hôte comme premier participant
        $db->execute(
            "INSERT INTO live_practice_participants (session_id, user_id) VALUES (?, ?)",
            [$sessionId, $userId]
        );

        $db->commit();

        $session = $db->fetchOne(
            "SELECT s.*, l.name AS language_name, l.code AS language_code,
                    u.first_name AS host_first_name, u.last_name AS host_last_name,
                    1 AS participant_count, TRUE AS is_participant
             FROM live_practice_sessions s
             JOIN languages l ON s.language_id = l.id
             JOIN users u ON s.host_user_id = u.id
             WHERE s.id = ?",
            [$sessionId]
        );

        Response::success($session, 'Session créée', 201);

    } catch (Exception $e) {
        $db->rollBack();
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// GET sessions
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'sessions') {
    try {
        $langId = isset($_GET['language_id']) ? (int)$_GET['language_id'] : null;
        $status = $_GET['status'] ?? null;

        $sql = "SELECT s.*, l.name AS language_name, l.code AS language_code,
                       u.first_name AS host_first_name, u.last_name AS host_last_name,
                       (SELECT COUNT(*) FROM live_practice_participants
                        WHERE session_id = s.id AND left_at IS NULL) AS participant_count
                FROM live_practice_sessions s
                JOIN languages l ON s.language_id = l.id
                JOIN users u ON s.host_user_id = u.id
                WHERE s.status IN ('scheduled', 'live')";

        $params = [];
        if ($langId) { $sql .= " AND s.language_id = ?"; $params[] = $langId; }
        if ($status)  { $sql .= " AND s.status = ?";     $params[] = $status; }
        $sql .= " ORDER BY s.status DESC, s.created_at DESC";

        $sessions = $db->fetchAll($sql, $params);

        // Marquer si l'utilisateur est participant
        foreach ($sessions as &$s) {
            $part = $db->fetchOne(
                "SELECT id FROM live_practice_participants WHERE session_id = ? AND user_id = ?",
                [$s['id'], $userId]
            );
            $s['is_participant'] = (bool) $part;
        }

        Response::success($sessions);

    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// GET detail
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'detail') {
    $sessionId = (int)($_GET['session_id'] ?? 0);
    if (!$sessionId) Response::error('session_id requis', 400);

    try {
        $session = $db->fetchOne(
            "SELECT s.*, l.name AS language_name, u.first_name AS host_first_name, u.last_name AS host_last_name
             FROM live_practice_sessions s
             JOIN languages l ON s.language_id = l.id
             JOIN users u ON s.host_user_id = u.id
             WHERE s.id = ?",
            [$sessionId]
        );
        if (!$session) Response::error('Session introuvable', 404);
        Response::success($session);
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// POST join
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'join') {
    $sessionId = (int)($_GET['session_id'] ?? 0);
    if (!$sessionId) Response::error('session_id requis', 400);

    try {
        // Si déjà présent: réactiver (left_at = NULL)
        $existing = $db->fetchOne(
            "SELECT id, left_at FROM live_practice_participants WHERE session_id = ? AND user_id = ?",
            [$sessionId, $userId]
        );

        if ($existing) {
            if ($existing['left_at'] !== null) {
                $db->execute(
                    "UPDATE live_practice_participants SET left_at = NULL, joined_at = CURRENT_TIMESTAMP
                     WHERE session_id = ? AND user_id = ?",
                    [$sessionId, $userId]
                );
            }
            Response::success(['already_participant' => true], 'Participant déjà inscrit');
        }

        // Vérifier capacité
        $session = $db->fetchOne(
            "SELECT max_participants,
                    (SELECT COUNT(*) FROM live_practice_participants
                     WHERE session_id = ? AND left_at IS NULL) AS current_count
             FROM live_practice_sessions WHERE id = ? AND status IN ('scheduled','live')",
            [$sessionId, $sessionId]
        );

        if (!$session) Response::error('Session introuvable ou terminée', 404);
        if ((int)$session['current_count'] >= (int)$session['max_participants']) {
            Response::error('Session complète', 400);
        }

        $db->execute(
            "INSERT INTO live_practice_participants (session_id, user_id) VALUES (?, ?)",
            [$sessionId, $userId]
        );

        Response::success(null, 'Session rejointe avec succès');

    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// POST leave
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'leave') {
    $sessionId = (int)($_GET['session_id'] ?? 0);
    if (!$sessionId) Response::error('session_id requis', 400);

    try {
        $db->execute(
            "UPDATE live_practice_participants SET left_at = CURRENT_TIMESTAMP
             WHERE session_id = ? AND user_id = ? AND left_at IS NULL",
            [$sessionId, $userId]
        );
        Response::success(null, 'Session quittée');
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// POST start  (hôte seulement)
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'start') {
    $sessionId = (int)($_GET['session_id'] ?? 0);
    if (!$sessionId) Response::error('session_id requis', 400);

    try {
        $session = $db->fetchOne(
            "SELECT id, host_user_id FROM live_practice_sessions WHERE id = ?",
            [$sessionId]
        );
        if (!$session || (int)$session['host_user_id'] !== $userId) {
            Response::forbidden("Vous n'êtes pas l'hôte");
        }
        $db->execute(
            "UPDATE live_practice_sessions SET status = 'live', started_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$sessionId]
        );
        Response::success(null, 'Session démarrée');
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// POST end  (hôte seulement)
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'end') {
    $sessionId = (int)($_GET['session_id'] ?? 0);
    if (!$sessionId) Response::error('session_id requis', 400);

    try {
        $session = $db->fetchOne(
            "SELECT id, host_user_id FROM live_practice_sessions WHERE id = ?",
            [$sessionId]
        );
        if (!$session || (int)$session['host_user_id'] !== $userId) {
            Response::forbidden("Vous n'êtes pas l'hôte");
        }
        $db->execute(
            "UPDATE live_practice_sessions
             SET status = 'completed', ended_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$sessionId]
        );
        // Marquer tous les participants comme partis
        $db->execute(
            "UPDATE live_practice_participants SET left_at = CURRENT_TIMESTAMP
             WHERE session_id = ? AND left_at IS NULL",
            [$sessionId]
        );
        Response::success(null, 'Session terminée');
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// GET participants
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'participants') {
    $sessionId = (int)($_GET['session_id'] ?? 0);
    if (!$sessionId) Response::error('session_id requis', 400);

    try {
        $participants = $db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_url, lp.joined_at
             FROM live_practice_participants lp
             JOIN users u ON lp.user_id = u.id
             WHERE lp.session_id = ? AND lp.left_at IS NULL
             ORDER BY lp.joined_at ASC",
            [$sessionId]
        );
        Response::success($participants);
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

Response::notFound('Endpoint non trouvé');
?>
