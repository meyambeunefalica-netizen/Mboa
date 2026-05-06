<?php
/**
 * API AI Tutor
 * Endpoints: sessions, messages
 */

require_once '../helpers/cors.php';
require_once '../config/config.php';
require_once '../helpers/response.php';
require_once '../helpers/jwt.php';
require_once '../helpers/mistral.php';

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
 * Créer une nouvelle session AI Tutor
 * POST /api/ai-tutor.php?action=create_session
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_session') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['language_id'])) {
        Response::error('language_id requis', 400);
    }
    
    try {
        $sessionType = $data['session_type'] ?? 'conversation';
        
        $db->execute(
            "INSERT INTO ai_tutor_sessions (user_id, language_id, session_type) VALUES (?, ?, ?)",
            [$userId, $data['language_id'], $sessionType]
        );
        
        $sessionId = $db->lastInsertId('ai_tutor_sessions_id_seq');
        
        // Message de bienvenue de l'Agent Mboa IA
        $languageName = $db->fetchOne("SELECT name FROM languages WHERE id = ?", [$data['language_id']])['name'];
        $welcomeMessage = "**Ôôô, anǎa !** *(Oui, c'est parti !)* 🎉 **Mbolo na mǐnǎ !** *(Bonjour à toi !)*\n\nJe suis l'Agent Mboa IA, ton tuteur pour apprendre le **" . $languageName . "**. 🌍\n\n**Première leçon express** :\n- **\"A yǐ ?\"** = *\"Ça va ?\"* (littéralement *\"C'est comment ?\"*)\n- **\"A yǐ mǎa\"** = *\"Ça va bien\"* / **\"A yǐ bǐt\"** = *\"Ça ne va pas\"*\n\n**À toi !** 🗣️ Réponds-moi en " . $languageName . " : *\"Mbolo ! A yǐ ?\"* → *(Je te dis bonjour et te demande comment tu vas !)*\n\n*(Je te corrigerai avec douceur si besoin !)* 😊\n\n**Petit défi** : Essaie d'ajouter *\"Mǐnǎ\"* (*\"toi\"*) à ta réponse pour faire plus naturel ! 💪";
        
        $db->execute(
            "INSERT INTO ai_tutor_messages (session_id, sender, content) VALUES (?, 'ai', ?)",
            [$sessionId, $welcomeMessage]
        );
        
        // Récupérer la session avec le message
        $session = $db->fetchOne(
            "SELECT * FROM ai_tutor_sessions WHERE id = ?",
            [$sessionId]
        );
        
        $messages = $db->fetchAll(
            "SELECT * FROM ai_tutor_messages WHERE session_id = ? ORDER BY created_at ASC",
            [$sessionId]
        );
        
        Response::success([
            'session' => $session,
            'messages' => $messages
        ], 'Session créée', 201);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer les sessions de l'utilisateur
 * GET /api/ai-tutor.php?action=sessions
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'sessions') {
    try {
        $sessions = $db->fetchAll(
            "SELECT s.*, l.name as language_name, l.code as language_code,
                    (SELECT COUNT(*) FROM ai_tutor_messages WHERE session_id = s.id) as message_count
             FROM ai_tutor_sessions s
             JOIN languages l ON s.language_id = l.id
             WHERE s.user_id = ? AND s.ended_at IS NULL
             ORDER BY s.started_at DESC",
            [$userId]
        );
        
        Response::success($sessions);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer les messages d'une session
 * GET /api/ai-tutor.php?action=messages&session_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'messages') {
    $sessionId = $_GET['session_id'] ?? null;
    
    if (!$sessionId) {
        Response::error('session_id requis', 400);
    }
    
    try {
        // Vérifier que la session appartient à l'utilisateur
        $session = $db->fetchOne(
            "SELECT id FROM ai_tutor_sessions WHERE id = ? AND user_id = ?",
            [$sessionId, $userId]
        );
        
        if (!$session) {
            Response::notFound('Session non trouvée');
        }
        
        $messages = $db->fetchAll(
            "SELECT * FROM ai_tutor_messages WHERE session_id = ? ORDER BY created_at ASC",
            [$sessionId]
        );
        
        Response::success($messages);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Envoyer un message dans une session
 * POST /api/ai-tutor.php?action=send_message&session_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'send_message') {
    $sessionId = $_GET['session_id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$sessionId || empty($data['content'])) {
        Response::error('session_id et content requis', 400);
    }
    
    try {
        // Vérifier que la session appartient à l'utilisateur et récupérer la langue
        $session = $db->fetchOne(
            "SELECT s.id, s.language_id, l.name as language_name 
             FROM ai_tutor_sessions s 
             JOIN languages l ON s.language_id = l.id
             WHERE s.id = ? AND s.user_id = ?",
            [$sessionId, $userId]
        );
        
        if (!$session) {
            Response::notFound('Session non trouvée');
        }
        
        // Insérer le message utilisateur
        $db->execute(
            "INSERT INTO ai_tutor_messages (session_id, sender, content) VALUES (?, 'user', ?)",
            [$sessionId, $data['content']]
        );
        
        // Récupérer l'historique de conversation pour le contexte
        $conversationHistory = $db->fetchAll(
            "SELECT sender, content FROM ai_tutor_messages WHERE session_id = ? ORDER BY created_at ASC",
            [$sessionId]
        );
        
        // Générer une réponse avec Mistral AI (Agent Mboa IA)
        $mistral = new MistralAI();
        $aiResponse = $mistral->generateTutorResponse($data['content'], $conversationHistory, $session['language_name']);
        
        $db->execute(
            "INSERT INTO ai_tutor_messages (session_id, sender, content) VALUES (?, 'ai', ?)",
            [$sessionId, $aiResponse]
        );
        
        // Récupérer tous les messages
        $messages = $db->fetchAll(
            "SELECT * FROM ai_tutor_messages WHERE session_id = ? ORDER BY created_at ASC",
            [$sessionId]
        );
        
        Response::success($messages, 'Message envoyé');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Terminer une session
 * POST /api/ai-tutor.php?action=end_session&session_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'end_session') {
    $sessionId = $_GET['session_id'] ?? null;
    
    if (!$sessionId) {
        Response::error('session_id requis', 400);
    }
    
    try {
        $db->execute(
            "UPDATE ai_tutor_sessions SET ended_at = NOW() WHERE id = ? AND user_id = ?",
            [$sessionId, $userId]
        );
        
        Response::success(null, 'Session terminée');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

Response::notFound('Endpoint non trouvé');
?>
