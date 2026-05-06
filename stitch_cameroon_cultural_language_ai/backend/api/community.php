<?php
/**
 * API Communauté
 * Endpoints: channels, messages, membres
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
 * Récupérer tous les channels
 * GET /api/community.php?action=channels
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'channels') {
    try {
        $languageId = $_GET['language_id'] ?? null;
        
        $sql = "SELECT c.*, l.name as language_name, l.code as language_code,
                       (SELECT COUNT(*) FROM channel_members WHERE channel_id = c.id) as member_count
                FROM channels c
                LEFT JOIN languages l ON c.language_id = l.id
                WHERE c.is_public = TRUE";
        
        $params = [];
        
        if ($languageId) {
            $sql .= " AND c.language_id = ?";
            $params[] = $languageId;
        }
        
        $sql .= " ORDER BY c.created_at DESC";
        
        $channels = $db->fetchAll($sql, $params);
        
        // Marquer les channels auxquels l'utilisateur est déjà membre
        foreach ($channels as &$channel) {
            $isMember = $db->fetchOne(
                "SELECT id FROM channel_members WHERE channel_id = ? AND user_id = ?",
                [$channel['id'], $userId]
            );
            $channel['is_member'] = $isMember ? true : false;
        }
        
        Response::success($channels);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Rejoindre un channel
 * POST /api/community.php?action=join&channel_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'join') {
    $channelId = $_GET['channel_id'] ?? null;
    
    if (!$channelId) {
        Response::error('channel_id requis', 400);
    }
    
    try {
        // Vérifier que le channel existe
        $channel = $db->fetchOne("SELECT id FROM channels WHERE id = ?", [$channelId]);
        if (!$channel) {
            Response::notFound('Channel introuvable');
        }
        
        // Vérifier si déjà membre
        $existing = $db->fetchOne(
            "SELECT id FROM channel_members WHERE channel_id = ? AND user_id = ?",
            [$channelId, $userId]
        );
        
        if ($existing) {
            Response::error('Vous êtes déjà membre de ce channel', 400);
        }
        
        // Ajouter comme membre
        $db->execute(
            "INSERT INTO channel_members (channel_id, user_id, role) VALUES (?, ?, 'member')",
            [$channelId, $userId]
        );
        
        Response::success(null, 'Channel rejoint avec succès');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Quitter un channel
 * POST /api/community.php?action=leave&channel_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'leave') {
    $channelId = $_GET['channel_id'] ?? null;
    
    if (!$channelId) {
        Response::error('channel_id requis', 400);
    }
    
    try {
        $db->execute(
            "DELETE FROM channel_members WHERE channel_id = ? AND user_id = ?",
            [$channelId, $userId]
        );
        
        Response::success(null, 'Channel quitté avec succès');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer les messages d'un channel
 * GET /api/community.php?action=messages&channel_id=X&limit=50&offset=0
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'messages') {
    $channelId = $_GET['channel_id'] ?? null;
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    
    if (!$channelId) {
        Response::error('channel_id requis', 400);
    }
    
    try {
        // Vérifier si l'utilisateur est membre
        $isMember = $db->fetchOne(
            "SELECT id FROM channel_members WHERE channel_id = ? AND user_id = ?",
            [$channelId, $userId]
        );
        
        if (!$isMember) {
            Response::forbidden('Vous n\'êtes pas membre de ce channel');
        }
        
        $messages = $db->fetchAll(
            "SELECT m.*, u.first_name, u.last_name, u.avatar_url
             FROM messages m
             JOIN users u ON m.user_id = u.id
             WHERE m.channel_id = ?
             ORDER BY m.created_at DESC
             LIMIT ? OFFSET ?",
            [$channelId, $limit, $offset]
        );
        
        // Inverser l'ordre pour avoir les plus récents en dernier
        $messages = array_reverse($messages);
        
        Response::success($messages);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Envoyer un message
 * POST /api/community.php?action=send_message
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'send_message') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['channel_id']) || empty($data['content'])) {
        Response::error('channel_id et content requis', 400);
    }
    
    try {
        // Vérifier si l'utilisateur est membre
        $isMember = $db->fetchOne(
            "SELECT id FROM channel_members WHERE channel_id = ? AND user_id = ?",
            [$data['channel_id'], $userId]
        );
        
        if (!$isMember) {
            Response::forbidden('Vous n\'êtes pas membre de ce channel');
        }
        
        // Insérer le message
        $db->execute(
            "INSERT INTO messages (channel_id, user_id, content, message_type) VALUES (?, ?, ?, 'text')",
            [$data['channel_id'], $userId, $data['content']]
        );
        
        $messageId = $db->lastInsertId('messages_id_seq');
        
        // Récupérer le message complet
        $message = $db->fetchOne(
            "SELECT m.*, u.first_name, u.last_name, u.avatar_url
             FROM messages m
             JOIN users u ON m.user_id = u.id
             WHERE m.id = ?",
            [$messageId]
        );
        
        Response::success($message, 'Message envoyé', 201);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer les membres en ligne d'un channel
 * GET /api/community.php?action=members&channel_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'members') {
    $channelId = $_GET['channel_id'] ?? null;
    
    if (!$channelId) {
        Response::error('channel_id requis', 400);
    }
    
    try {
        $members = $db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_url, cm.role, cm.joined_at
             FROM channel_members cm
             JOIN users u ON cm.user_id = u.id
             WHERE cm.channel_id = ?
             ORDER BY cm.joined_at ASC",
            [$channelId]
        );
        
        Response::success($members);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Recherche d'utilisateurs par pseudo
 * GET /api/community.php?action=search&query=...
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'search') {
    $query = $_GET['query'] ?? '';
    
    if (strlen($query) < 2) {
        Response::error('Minimum 2 caracteres', 400);
    }
    
    try {
        $users = $db->fetchAll(
            "SELECT id, first_name, last_name, level FROM users 
             WHERE (first_name ILIKE ? OR last_name ILIKE ?) AND id != ?
             ORDER BY first_name LIMIT 20",
            ["%{$query}%", "%{$query}%", $userId]
        );
        
        Response::success($users);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Envoyer un message direct
 * POST /api/community.php?action=send_dm
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'send_dm') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['receiver_id']) || empty($data['content'])) {
        Response::error('receiver_id et content requis', 400);
    }
    
    try {
        // Vérifier que le destinataire existe
        $receiver = $db->fetchOne("SELECT id FROM users WHERE id = ?", [$data['receiver_id']]);
        if (!$receiver) {
            Response::notFound('Utilisateur introuvable');
        }
        
        $db->execute(
            "INSERT INTO direct_messages (sender_id, receiver_id, content) VALUES (?, ?, ?)",
            [$userId, $data['receiver_id'], $data['content']]
        );
        
        $dmId = $db->lastInsertId('direct_messages_id_seq');
        
        $message = $db->fetchOne(
            "SELECT dm.*, u.first_name as sender_first_name, u.last_name as sender_last_name
             FROM direct_messages dm
             JOIN users u ON dm.sender_id = u.id
             WHERE dm.id = ?",
            [$dmId]
        );
        
        Response::success($message, 'Message envoyé', 201);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer les messages directs avec un utilisateur
 * GET /api/community.php?action=dms&with_user_id=X&limit=50&offset=0
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'dms') {
    $withUserId = $_GET['with_user_id'] ?? null;
    $limit = $_GET['limit'] ?? 50;
    $offset = $_GET['offset'] ?? 0;
    
    if (!$withUserId) {
        Response::error('with_user_id requis', 400);
    }
    
    try {
        // Marquer les messages reçus comme lus
        $db->execute(
            "UPDATE direct_messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ?",
            [$withUserId, $userId]
        );
        
        $messages = $db->fetchAll(
            "SELECT dm.*, 
                    u.first_name as sender_first_name, u.last_name as sender_last_name
             FROM direct_messages dm
             JOIN users u ON dm.sender_id = u.id
             WHERE (dm.sender_id = ? AND dm.receiver_id = ?)
                OR (dm.sender_id = ? AND dm.receiver_id = ?)
             ORDER BY dm.created_at ASC
             LIMIT ? OFFSET ?",
            [$userId, $withUserId, $withUserId, $userId, $limit, $offset]
        );
        
        Response::success($messages);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer les conversations récentes (derniers contacts)
 * GET /api/community.php?action=dm_conversations
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'dm_conversations') {
    try {
        $conversations = $db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.level,
                    (SELECT content FROM direct_messages 
                     WHERE (sender_id = ? AND receiver_id = u.id) 
                        OR (sender_id = u.id AND receiver_id = ?)
                     ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM direct_messages 
                     WHERE (sender_id = ? AND receiver_id = u.id) 
                        OR (sender_id = u.id AND receiver_id = ?)
                     ORDER BY created_at DESC LIMIT 1) as last_message_at,
                    (SELECT COUNT(*) FROM direct_messages 
                     WHERE sender_id = u.id AND receiver_id = ? AND is_read = FALSE) as unread_count
             FROM direct_messages dm
             JOIN users u ON (dm.sender_id = u.id OR dm.receiver_id = u.id)
             WHERE (dm.sender_id = ? OR dm.receiver_id = ?)
               AND u.id != ?
             GROUP BY u.id, u.first_name, u.last_name, u.level
             ORDER BY last_message_at DESC",
            [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]
        );
        
        Response::success($conversations);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

Response::notFound('Endpoint non trouvé');
?>
