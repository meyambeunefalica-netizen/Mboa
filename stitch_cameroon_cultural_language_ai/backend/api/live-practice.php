<?php
/**
 * API Live Practice
 * Endpoints: sessions, participants
 */

require_once '../helpers/cors.php';
require_once '../config/config.php';
require_once '../helpers/response.php';
require_once '../helpers/jwt.php';

// Middleware d'authentification
function authenticate() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        Response::unauthorized('Token manquant');
    }
    
    $token = $matches[1];
    
    if (!JWT::verify($token)) {
        Response::unauthorized('Token invalide ou expiré');
    }
    
    return JWT::decode($token);
}

$db = Database::getInstance();
$payload = authenticate();
$userId = $payload['user_id'];

/**
 * Créer une nouvelle session Live Practice
 * POST /api/live-practice.php?action=create_session
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_session') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['language_id']) || empty($data['title'])) {
        Response::error('language_id et title requis', 400);
    }
    
    try {
        $scheduledAt = $data['scheduled_at'] ?? null;
        $maxParticipants = $data['max_participants'] ?? 10;
        $description = $data['description'] ?? '';
        
        $db->execute(
            "INSERT INTO live_practice_sessions (host_user_id, language_id, title, description, max_participants, scheduled_at, status) VALUES (?, ?, ?, ?, ?, ?, 'scheduled')",
            [$userId, $data['language_id'], $data['title'], $description, $maxParticipants, $scheduledAt]
        );
        
        $sessionId = $db->lastInsertId('live_practice_sessions_id_seq');
        
        // Ajouter l'hôte comme participant
        $db->execute(
            "INSERT INTO live_practice_participants (session_id, user_id) VALUES (?, ?)",
            [$sessionId, $userId]
        );
        
        $session = $db->fetchOne(
            "SELECT s.*, l.name as language_name, l.code as language_code,
                    u.first_name as host_first_name, u.last_name as host_last_name
             FROM live_practice_sessions s
             JOIN languages l ON s.language_id = l.id
             JOIN users u ON s.host_user_id = u.id
             WHERE s.id = ?",
            [$sessionId]
        );
        
        Response::success($session, 'Session créée', 201);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer toutes les sessions disponibles
 * GET /api/live-practice.php?action=sessions
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'sessions') {
    try {
        $languageId = $_GET['language_id'] ?? null;
        $status = $_GET['status'] ?? null;
        
        $sql = "SELECT s.*, l.name as language_name, l.code as language_code,
                       u.first_name as host_first_name, u.last_name as host_last_name,
                       (SELECT COUNT(*) FROM live_practice_participants WHERE session_id = s.id) as participant_count
                FROM live_practice_sessions s
                JOIN languages l ON s.language_id = l.id
                JOIN users u ON s.host_user_id = u.id
                WHERE s.status IN ('scheduled', 'live')";
        
        $params = [];
        
        if ($languageId) {
            $sql .= " AND s.language_id = ?";
            $params[] = $languageId;
        }
        
        if ($status) {
            $sql .= " AND s.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY s.scheduled_at ASC";
        
        $sessions = $db->fetchAll($sql, $params);
        
        // Marquer les sessions auxquelles l'utilisateur participe
        foreach ($sessions as &$session) {
            $isParticipant = $db->fetchOne(
                "SELECT id FROM live_practice_participants WHERE session_id = ? AND user_id = ?",
                [$session['id'], $userId]
            );
            $session['is_participant'] = $isParticipant ? true : false;
        }
        
        Response::success($sessions);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Rejoindre une session
 * POST /api/live-practice.php?action=join&session_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'join') {
    $sessionId = $_GET['session_id'] ?? null;
    
    if (!$sessionId) {
        Response::error('session_id requis', 400);
    }
    
    try {
        // Vérifier si déjà participant
        $existing = $db->fetchOne(
            "SELECT id FROM live_practice_participants WHERE session_id = ? AND user_id = ?",
            [$sessionId, $userId]
        );
        
        if ($existing) {
            Response::error('Vous participez déjà à cette session', 400);
        }
        
        // Vérifier le nombre maximum de participants
        $session = $db->fetchOne(
            "SELECT max_participants, 
                    (SELECT COUNT(*) FROM live_practice_participants WHERE session_id = ?) as current_count
             FROM live_practice_sessions WHERE id = ?",
            [$sessionId, $sessionId]
        );
        
        if ($session['current_count'] >= $session['max_participants']) {
            Response::error('Session complète', 400);
        }
        
        // Ajouter comme participant
        $db->execute(
            "INSERT INTO live_practice_participants (session_id, user_id) VALUES (?, ?)",
            [$sessionId, $userId]
        );
        
        Response::success(null, 'Session rejointe avec succès');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Quitter une session
 * POST /api/live-practice.php?action=leave&session_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'leave') {
    $sessionId = $_GET['session_id'] ?? null;
    
    if (!$sessionId) {
        Response::error('session_id requis', 400);
    }
    
    try {
        $db->execute(
            "UPDATE live_practice_participants SET left_at = NOW() WHERE session_id = ? AND user_id = ?",
            [$sessionId, $userId]
        );
        
        Response::success(null, 'Session quittée avec succès');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Démarrer une session (pour l'hôte)
 * POST /api/live-practice.php?action=start&session_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'start') {
    $sessionId = $_GET['session_id'] ?? null;
    
    if (!$sessionId) {
        Response::error('session_id requis', 400);
    }
    
    try {
        // Vérifier que l'utilisateur est l'hôte
        $session = $db->fetchOne(
            "SELECT id, host_user_id FROM live_practice_sessions WHERE id = ?",
            [$sessionId]
        );
        
        if (!$session || $session['host_user_id'] != $userId) {
            Response::forbidden('Vous n\'êtes pas l\'hôte de cette session');
        }
        
        $db->execute(
            "UPDATE live_practice_sessions SET status = 'live', started_at = NOW() WHERE id = ?",
            [$sessionId]
        );
        
        Response::success(null, 'Session démarrée');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Terminer une session (pour l'hôte)
 * POST /api/live-practice.php?action=end&session_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'end') {
    $sessionId = $_GET['session_id'] ?? null;
    
    if (!$sessionId) {
        Response::error('session_id requis', 400);
    }
    
    try {
        // Vérifier que l'utilisateur est l'hôte
        $session = $db->fetchOne(
            "SELECT id, host_user_id FROM live_practice_sessions WHERE id = ?",
            [$sessionId]
        );
        
        if (!$session || $session['host_user_id'] != $userId) {
            Response::forbidden('Vous n\'êtes pas l\'hôte de cette session');
        }
        
        $db->execute(
            "UPDATE live_practice_sessions SET status = 'completed', ended_at = NOW() WHERE id = ?",
            [$sessionId]
        );
        
        Response::success(null, 'Session terminée');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer les participants d'une session
 * GET /api/live-practice.php?action=participants&session_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'participants') {
    $sessionId = $_GET['session_id'] ?? null;
    
    if (!$sessionId) {
        Response::error('session_id requis', 400);
    }
    
    try {
        $participants = $db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_url, lp.joined_at, lp.left_at
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
