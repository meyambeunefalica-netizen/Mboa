<?php
/**
 * API d'authentification
 * Endpoints: inscription, connexion, déconnexion
 */

require_once '../helpers/cors.php';
require_once '../config/config.php';
require_once '../helpers/response.php';
require_once '../helpers/jwt.php';

$db = Database::getInstance();

/**
 * Inscription d'un nouvel utilisateur
 * POST /api/auth.php?action=register
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    if (empty($data['email']) || empty($data['password']) || empty($data['first_name']) || empty($data['last_name'])) {
        Response::error('Tous les champs sont requis', 400);
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        Response::error('Email invalide', 400);
    }
    
    if (strlen($data['password']) < 6) {
        Response::error('Le mot de passe doit contenir au moins 6 caractères', 400);
    }
    
    try {
        // Vérifier si l'email existe déjà
        $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$data['email']]);
        if ($existingUser) {
            Response::error('Cet email est déjà utilisé', 409);
        }
        
        // Hasher le mot de passe
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insérer l'utilisateur
        $db->execute(
            "INSERT INTO users (email, password_hash, first_name, last_name, level) VALUES (?, ?, ?, ?, ?)",
            [$data['email'], $passwordHash, $data['first_name'], $data['last_name'], 'Beginner']
        );
        
        $userId = $db->lastInsertId('users_id_seq');
        
        // Créer une progression initiale pour Bulu (langue par défaut)
        $db->execute(
            "INSERT INTO user_language_progress (user_id, language_id, proficiency_level) VALUES (?, 1, 'Beginner')",
            [$userId]
        );
        
        // Générer le token JWT
        $token = JWT::encode(['user_id' => $userId, 'email' => $data['email']]);
        
        // Récupérer les infos de l'utilisateur
        $user = $db->fetchOne(
            "SELECT id, email, first_name, last_name, avatar_url, level, created_at FROM users WHERE id = ?",
            [$userId]
        );
        
        Response::success([
            'user' => $user,
            'token' => $token
        ], 'Inscription réussie', 201);
        
    } catch (Exception $e) {
        Response::serverError('Erreur lors de l\'inscription: ' . $e->getMessage());
    }
}

/**
 * Connexion d'un utilisateur
 * POST /api/auth.php?action=login
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    if (empty($data['email']) || empty($data['password'])) {
        Response::error('Email et mot de passe requis', 400);
    }
    
    try {
        // Récupérer l'utilisateur
        $user = $db->fetchOne(
            "SELECT id, email, password_hash, first_name, last_name, avatar_url, level, is_active FROM users WHERE email = ?",
            [$data['email']]
        );
        
        if (!$user) {
            Response::error('Email ou mot de passe incorrect', 401);
        }
        
        if (!$user['is_active']) {
            Response::error('Compte désactivé', 403);
        }
        
        // Vérifier le mot de passe
        if (!password_verify($data['password'], $user['password_hash'])) {
            Response::error('Email ou mot de passe incorrect', 401);
        }
        
        // Mettre à jour last_login
        $db->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        
        // Générer le token JWT
        $token = JWT::encode(['user_id' => $user['id'], 'email' => $user['email']]);
        
        // Retirer le hash du mot de passe de la réponse
        unset($user['password_hash']);
        
        Response::success([
            'user' => $user,
            'token' => $token
        ], 'Connexion réussie');
        
    } catch (Exception $e) {
        Response::serverError('Erreur lors de la connexion: ' . $e->getMessage());
    }
}

/**
 * Vérifier si un token est valide
 * GET /api/auth.php?action=verify
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'verify') {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        Response::unauthorized('Token manquant');
    }
    
    $token = $matches[1];
    
    if (!JWT::verify($token)) {
        Response::unauthorized('Token invalide ou expiré');
    }
    
    $payload = JWT::decode($token);
    
    // Récupérer les infos de l'utilisateur
    $user = $db->fetchOne(
        "SELECT id, email, first_name, last_name, avatar_url, level, is_active FROM users WHERE id = ?",
        [$payload['user_id']]
    );
    
    if (!$user || !$user['is_active']) {
        Response::unauthorized('Utilisateur non trouvé ou désactivé');
    }
    
    Response::success(['user' => $user], 'Token valide');
}

/**
 * Déconnexion (côté client, supprimer le token)
 * POST /api/auth.php?action=logout
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    // La déconnexion est gérée côté client en supprimant le token
    Response::success(null, 'Déconnexion réussie');
}

Response::notFound('Endpoint non trouvé');
?>
