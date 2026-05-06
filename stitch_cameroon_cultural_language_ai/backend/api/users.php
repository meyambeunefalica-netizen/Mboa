<?php
/**
 * API Utilisateurs
 * Endpoints: profil, progression, mise à jour
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
 * Récupérer le profil utilisateur
 * GET /api/users.php
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['action'])) {
    try {
        $user = $db->fetchOne(
            "SELECT id, email, first_name, last_name, avatar_url, level, created_at, last_login FROM users WHERE id = ?",
            [$userId]
        );
        
        if (!$user) {
            Response::notFound('Utilisateur non trouvé');
        }
        
        // Récupérer la progression par langue
        $progress = $db->fetchAll(
            "SELECT ulp.*, l.name as language_name, l.code as language_code 
             FROM user_language_progress ulp 
             JOIN languages l ON ulp.language_id = l.id 
             WHERE ulp.user_id = ?",
            [$userId]
        );
        
        // Récupérer l'objectif quotidien
        $today = date('Y-m-d');
        $dailyGoal = $db->fetchOne(
            "SELECT * FROM daily_goals WHERE user_id = ? AND date = ?",
            [$userId, $today]
        );
        
        if (!$dailyGoal) {
            // Créer un objectif par défaut
            $db->execute(
                "INSERT INTO daily_goals (user_id, date, target_minutes, target_lessons) VALUES (?, ?, 30, 1)",
                [$userId, $today]
            );
            $dailyGoal = [
                'target_minutes' => 30,
                'target_lessons' => 1,
                'completed_minutes' => 0,
                'completed_lessons' => 0,
                'is_achieved' => false
            ];
        }
        
        Response::success([
            'user' => $user,
            'progress' => $progress,
            'daily_goal' => $dailyGoal
        ]);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Mettre à jour le profil utilisateur
 * PUT /api/users.php?action=update
 */
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $updates = [];
        $params = [];
        
        if (isset($data['first_name'])) {
            $updates[] = "first_name = ?";
            $params[] = $data['first_name'];
        }
        
        if (isset($data['last_name'])) {
            $updates[] = "last_name = ?";
            $params[] = $data['last_name'];
        }
        
        if (isset($data['avatar_url'])) {
            $updates[] = "avatar_url = ?";
            $params[] = $data['avatar_url'];
        }
        
        if (empty($updates)) {
            Response::error('Aucune donnée à mettre à jour', 400);
        }
        
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $db->execute($sql, $params);
        
        // Récupérer l'utilisateur mis à jour
        $user = $db->fetchOne(
            "SELECT id, email, first_name, last_name, avatar_url, level FROM users WHERE id = ?",
            [$userId]
        );
        
        Response::success($user, 'Profil mis à jour');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Mettre à jour la progression d'une langue
 * POST /api/users.php?action=progress
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'progress') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['language_id'])) {
        Response::error('language_id requis', 400);
    }
    
    try {
        // Vérifier si la progression existe
        $existing = $db->fetchOne(
            "SELECT id FROM user_language_progress WHERE user_id = ? AND language_id = ?",
            [$userId, $data['language_id']]
        );
        
        if ($existing) {
            // Mettre à jour
            $updates = [];
            $params = [];
            
            if (isset($data['xp_points'])) {
                $updates[] = "xp_points = xp_points + ?";
                $params[] = $data['xp_points'];
            }
            
            if (isset($data['lessons_completed'])) {
                $updates[] = "lessons_completed = lessons_completed + ?";
                $params[] = $data['lessons_completed'];
            }
            
            if (isset($data['streak_days'])) {
                $updates[] = "streak_days = ?";
                $params[] = $data['streak_days'];
            }
            
            if (isset($data['proficiency_level'])) {
                $updates[] = "proficiency_level = ?";
                $params[] = $data['proficiency_level'];
            }
            
            $updates[] = "last_practiced_at = NOW()";
            
            $params[] = $userId;
            $params[] = $data['language_id'];
            
            $sql = "UPDATE user_language_progress SET " . implode(', ', $updates) . " WHERE user_id = ? AND language_id = ?";
            $db->execute($sql, $params);
        } else {
            // Créer
            $db->execute(
                "INSERT INTO user_language_progress (user_id, language_id, proficiency_level, xp_points, lessons_completed, last_practiced_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [$userId, $data['language_id'], $data['proficiency_level'] ?? 'Beginner', $data['xp_points'] ?? 0, $data['lessons_completed'] ?? 0]
            );
        }
        
        // Mettre à jour l'objectif quotidien
        $today = date('Y-m-d');
        if (isset($data['minutes_spent'])) {
            $db->execute(
                "UPDATE daily_goals SET completed_minutes = completed_minutes + ? WHERE user_id = ? AND date = ?",
                [$data['minutes_spent'], $userId, $today]
            );
        }
        
        if (isset($data['lesson_completed']) && $data['lesson_completed']) {
            $db->execute(
                "UPDATE daily_goals SET completed_lessons = completed_lessons + 1 WHERE user_id = ? AND date = ?",
                [$userId, $today]
            );
        }
        
        Response::success(null, 'Progression mise à jour');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer toutes les langues disponibles
 * GET /api/users.php?action=languages
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'languages') {
    try {
        $languages = $db->fetchAll(
            "SELECT * FROM languages WHERE is_active = TRUE ORDER BY name"
        );
        
        Response::success($languages);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

Response::notFound('Endpoint non trouvé');
?>
