<?php
/**
 * API Cours et Leçons
 * Endpoints: cours, leçons, progression
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
 * Récupérer tous les cours
 * GET /api/courses.php
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['action'])) {
    try {
        $languageId = $_GET['language_id'] ?? null;
        
        $sql = "SELECT c.*, l.name as language_name, l.code as language_code 
                FROM courses c 
                JOIN languages l ON c.language_id = l.id 
                WHERE c.is_active = TRUE";
        
        $params = [];
        
        if ($languageId) {
            $sql .= " AND c.language_id = ?";
            $params[] = $languageId;
        }
        
        $sql .= " ORDER BY c.created_at DESC";
        
        $courses = $db->fetchAll($sql, $params);
        
        // Pour chaque cours, ajouter la progression de l'utilisateur
        foreach ($courses as &$course) {
            $progress = $db->fetchOne(
                "SELECT COUNT(*) as total_lessons, 
                        SUM(CASE WHEN lp.is_completed = TRUE THEN 1 ELSE 0 END) as completed_lessons
                 FROM lessons l
                 LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ?
                 WHERE l.course_id = ? AND l.is_active = TRUE",
                [$userId, $course['id']]
            );
            
            $course['total_lessons'] = $progress['total_lessons'] ?? 0;
            $course['completed_lessons'] = $progress['completed_lessons'] ?? 0;
            $course['progress_percentage'] = $course['total_lessons'] > 0 
                ? round(($course['completed_lessons'] / $course['total_lessons']) * 100) 
                : 0;
        }
        
        Response::success($courses);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer les leçons d'un cours
 * GET /api/courses.php?action=lessons&course_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'lessons') {
    $courseId = $_GET['course_id'] ?? null;
    
    if (!$courseId) {
        Response::error('course_id requis', 400);
    }
    
    try {
        $lessons = $db->fetchAll(
            "SELECT l.*, 
                    CASE WHEN lp.is_completed = TRUE THEN 1 ELSE 0 END as is_completed,
                    lp.score
             FROM lessons l
             LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ?
             WHERE l.course_id = ? AND l.is_active = TRUE
             ORDER BY l.lesson_order",
            [$userId, $courseId]
        );
        
        Response::success($lessons);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Marquer une leçon comme terminée
 * POST /api/courses.php?action=complete&lesson_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'complete') {
    $lessonId = $_GET['lesson_id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$lessonId) {
        Response::error('lesson_id requis', 400);
    }
    
    try {
        $score = $data['score'] ?? null;
        
        // Vérifier si la progression existe
        $existing = $db->fetchOne(
            "SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?",
            [$userId, $lessonId]
        );
        
        if ($existing) {
            // Mettre à jour
            $db->execute(
                "UPDATE lesson_progress SET is_completed = TRUE, score = ?, completed_at = NOW(), attempts = attempts + 1 WHERE user_id = ? AND lesson_id = ?",
                [$score, $userId, $lessonId]
            );
        } else {
            // Créer
            $db->execute(
                "INSERT INTO lesson_progress (user_id, lesson_id, is_completed, score, completed_at, attempts) VALUES (?, ?, TRUE, ?, NOW(), 1)",
                [$userId, $lessonId, $score]
            );
        }
        
        // Mettre à jour la progression globale de la langue
        $lesson = $db->fetchOne("SELECT course_id FROM lessons WHERE id = ?", [$lessonId]);
        $course = $db->fetchOne("SELECT language_id FROM courses WHERE id = ?", [$lesson['course_id']]);
        
        $db->execute(
            "UPDATE user_language_progress SET lessons_completed = lessons_completed + 1, last_practiced_at = NOW() WHERE user_id = ? AND language_id = ?",
            [$userId, $course['language_id']]
        );
        
        Response::success(null, 'Leçon marquée comme terminée');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer le vocabulaire d'une langue
 * GET /api/courses.php?action=vocabulary&language_id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'vocabulary') {
    $languageId = $_GET['language_id'] ?? null;
    $category = $_GET['category'] ?? null;

    if (!$languageId) {
        Response::error('language_id requis', 400);
    }

    try {
        // Vérifier si la table vocabulary existe
        $tableExists = $db->fetchOne(
            "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'vocabulary')"
        );

        if (!$tableExists || !$tableExists['exists']) {
            // Retourner un tableau vide si la table n'existe pas encore
            Response::success([]);
        }

        $sql = "SELECT v.*,
                       CASE WHEN uv.mastery_level IS NOT NULL THEN uv.mastery_level ELSE 0 END as mastery_level
                FROM vocabulary v
                LEFT JOIN user_vocabulary uv ON v.id = uv.vocabulary_id AND uv.user_id = ?
                WHERE v.language_id = ?";

        $params = [$userId, $languageId];

        if ($category) {
            $sql .= " AND v.category = ?";
            $params[] = $category;
        }

        $sql .= " ORDER BY v.word";

        $vocabulary = $db->fetchAll($sql, $params);

        Response::success($vocabulary);

    } catch (Exception $e) {
        // En cas d'erreur, retourner un tableau vide pour ne pas bloquer l'interface
        Response::success([]);
    }
}

/**
 * Mettre à jour la maîtrise d'un mot de vocabulaire
 * POST /api/courses.php?action=vocabulary_mastery
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'vocabulary_mastery') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['vocabulary_id']) || !isset($data['mastery_level'])) {
        Response::error('vocabulary_id et mastery_level requis', 400);
    }
    
    try {
        // Vérifier si l'entrée existe
        $existing = $db->fetchOne(
            "SELECT id FROM user_vocabulary WHERE user_id = ? AND vocabulary_id = ?",
            [$userId, $data['vocabulary_id']]
        );
        
        if ($existing) {
            $db->execute(
                "UPDATE user_vocabulary SET mastery_level = ?, last_reviewed_at = NOW() WHERE user_id = ? AND vocabulary_id = ?",
                [$data['mastery_level'], $userId, $data['vocabulary_id']]
            );
        } else {
            $db->execute(
                "INSERT INTO user_vocabulary (user_id, vocabulary_id, mastery_level, last_reviewed_at) VALUES (?, ?, ?, NOW())",
                [$userId, $data['vocabulary_id'], $data['mastery_level']]
            );
        }
        
        Response::success(null, 'Maîtrise mise à jour');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

Response::notFound('Endpoint non trouvé');
?>
