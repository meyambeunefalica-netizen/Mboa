<?php
/**
 * API de signalisation WebRTC
 * Remplace totalement livekit.php
 *
 * Endpoints:
 *   POST ?action=send     – Envoyer un signal (offer/answer/ice-candidate/leave)
 *   GET  ?action=poll     – Long-poll pour recevoir les signaux entrants
 *   GET  ?action=peers    – Lister les pairs actifs dans la session
 *   POST ?action=ping     – Maintenir sa présence (heartbeat)
 */

require_once '../config/config.php';
require_once '../helpers/response.php';
require_once '../helpers/jwt.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Authentification ──────────────────────────────────────────────────────────
function authenticate(): array {
    $headers  = getallheaders();
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

// ── Helpers ───────────────────────────────────────────────────────────────────
function getSessionOrFail(object $db, int $sessionId, int $userId): array {
    // Vérifie que la session existe et que l'utilisateur en est participant
    $session = $db->fetchOne(
        "SELECT s.id, s.status, s.host_user_id FROM live_practice_sessions s
         JOIN live_practice_participants p ON p.session_id = s.id
         WHERE s.id = ? AND p.user_id = ? AND p.left_at IS NULL",
        [$sessionId, $userId]
    );
    if (!$session) {
        Response::error('Session introuvable ou vous n\'êtes pas participant', 403);
    }
    return $session;
}

function purgeOldSignals(object $db): void {
    // Supprime les signaux de plus de 2 minutes pour éviter l'accumulation
    $db->execute(
        "DELETE FROM webrtc_signals WHERE created_at < NOW() - INTERVAL '2 minutes'"
    );
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$db      = Database::getInstance();
$payload = authenticate();
$userId  = (int) $payload['user_id'];
$action  = $_GET['action'] ?? '';

// ══════════════════════════════════════════════════════════════════════════════
// POST ?action=send  — Publier un signal WebRTC
// Body: { session_id, to_user_id (optionnel), type, payload }
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'send') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $sessionId = (int)($data['session_id'] ?? 0);
    $toUserId  = isset($data['to_user_id']) ? (int)$data['to_user_id'] : null;
    $type      = $data['type']    ?? '';
    $signalPayload = isset($data['payload']) ? json_encode($data['payload']) : '';

    $allowedTypes = ['offer', 'answer', 'ice-candidate', 'leave', 'ping'];
    if (!$sessionId || !in_array($type, $allowedTypes, true) || !$signalPayload) {
        Response::error('Paramètres invalides: session_id, type et payload requis', 400);
    }

    getSessionOrFail($db, $sessionId, $userId);
    purgeOldSignals($db);

    $db->execute(
        "INSERT INTO webrtc_signals (session_id, from_user_id, to_user_id, signal_type, payload)
         VALUES (?, ?, ?, ?, ?)",
        [$sessionId, $userId, $toUserId, $type, $signalPayload]
    );

    Response::success(['ok' => true], 'Signal envoyé');
}

// ══════════════════════════════════════════════════════════════════════════════
// GET ?action=poll&session_id=X&since_id=Y  — Long-poll pour signaux entrants
// Attend jusqu'à ~20s en boucle, retourne dès qu'un nouveau signal arrive
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'poll') {
    $sessionId = (int)($_GET['session_id'] ?? 0);
    $sinceId   = (int)($_GET['since_id']   ?? 0);

    if (!$sessionId) {
        Response::error('session_id requis', 400);
    }

    getSessionOrFail($db, $sessionId, $userId);

    // Long-polling: on interroge toutes les 500ms pendant max 20s
    $maxWait  = 20;   // secondes
    $interval = 500000; // microsecondes (0.5s)
    $elapsed  = 0;
    $limit    = $maxWait * 1_000_000;

    set_time_limit(30);

    while ($elapsed < $limit) {
        $signals = $db->fetchAll(
            "SELECT id, from_user_id, to_user_id, signal_type, payload, created_at
             FROM webrtc_signals
             WHERE session_id = ?
               AND id > ?
               AND consumed = FALSE
               AND (to_user_id = ? OR to_user_id IS NULL)
               AND from_user_id != ?
             ORDER BY id ASC
             LIMIT 20",
            [$sessionId, $sinceId, $userId, $userId]
        );

        if (!empty($signals)) {
            // Décoder le payload JSON pour chaque signal
            foreach ($signals as &$s) {
                $s['payload'] = json_decode($s['payload'], true);
            }

            // Marquer comme consommés pour cet utilisateur
            // (on garde les signaux broadcast pour les autres)
            $ids = array_column($signals, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($ids, [$userId]);
            $db->execute(
                "UPDATE webrtc_signals SET consumed = TRUE
                 WHERE id IN ($placeholders) AND (to_user_id = ? OR to_user_id IS NULL)",
                $params
            );

            Response::success([
                'signals'  => $signals,
                'since_id' => max(array_column($signals, 'id')),
            ]);
        }

        usleep($interval);
        $elapsed += $interval;
    }

    // Timeout: retourner un tableau vide pour que le client reboucle
    Response::success(['signals' => [], 'since_id' => $sinceId]);
}

// ══════════════════════════════════════════════════════════════════════════════
// GET ?action=peers&session_id=X  — Lister les pairs connectés
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'peers') {
    $sessionId = (int)($_GET['session_id'] ?? 0);
    if (!$sessionId) {
        Response::error('session_id requis', 400);
    }

    getSessionOrFail($db, $sessionId, $userId);

    $peers = $db->fetchAll(
        "SELECT u.id, u.first_name, u.last_name, u.avatar_url, lp.joined_at
         FROM live_practice_participants lp
         JOIN users u ON lp.user_id = u.id
         WHERE lp.session_id = ? AND lp.left_at IS NULL AND lp.user_id != ?
         ORDER BY lp.joined_at ASC",
        [$sessionId, $userId]
    );

    Response::success($peers);
}

// ══════════════════════════════════════════════════════════════════════════════
// POST ?action=ping&session_id=X  — Heartbeat (maintenir présence)
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'ping') {
    $sessionId = (int)(($_GET['session_id'] ?? 0) ?: (json_decode(file_get_contents('php://input'), true)['session_id'] ?? 0));
    if (!$sessionId) {
        Response::error('session_id requis', 400);
    }

    // Mise à jour du timestamp joined_at pour signaler qu'on est encore là
    $db->execute(
        "UPDATE live_practice_participants SET joined_at = CURRENT_TIMESTAMP
         WHERE session_id = ? AND user_id = ? AND left_at IS NULL",
        [$sessionId, $userId]
    );

    Response::success(['ok' => true]);
}

Response::notFound('Endpoint non trouvé');
?>
