<?php
/**
 * API Bibliothèque (Contenu Culturel)
 * Endpoints: articles, vidéos, contenu culturel
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
 * Récupérer tout le contenu culturel
 * GET /api/library.php
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['action'])) {
    try {
        $languageId = $_GET['language_id'] ?? null;
        $category = $_GET['category'] ?? null;
        $contentType = $_GET['content_type'] ?? null;
        
        $sql = "SELECT c.*, l.name as language_name, l.code as language_code
                FROM cultural_content c
                JOIN languages l ON c.language_id = l.id
                WHERE 1=1";
        
        $params = [];
        
        if ($languageId) {
            $sql .= " AND c.language_id = ?";
            $params[] = $languageId;
        }
        
        if ($category) {
            $sql .= " AND c.category = ?";
            $params[] = $category;
        }
        
        if ($contentType) {
            $sql .= " AND c.content_type = ?";
            $params[] = $contentType;
        }
        
        $sql .= " ORDER BY c.published_at DESC";
        
        $content = $db->fetchAll($sql, $params);
        
        Response::success($content);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer un contenu spécifique
 * GET /api/library.php?action=detail&id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'detail') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        Response::error('id requis', 400);
    }
    
    try {
        $content = $db->fetchOne(
            "SELECT c.*, l.name as language_name, l.code as language_code
             FROM cultural_content c
             JOIN languages l ON c.language_id = l.id
             WHERE c.id = ?",
            [$id]
        );
        
        if (!$content) {
            Response::notFound('Contenu non trouvé');
        }
        
        Response::success($content);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer les catégories disponibles
 * GET /api/library.php?action=categories
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'categories') {
    try {
        $categories = $db->fetchAll(
            "SELECT DISTINCT category FROM cultural_content WHERE category IS NOT NULL ORDER BY category"
        );
        
        $categoryList = array_map(function($item) {
            return $item['category'];
        }, $categories);
        
        Response::success($categoryList);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Récupérer les types de contenu disponibles
 * GET /api/library.php?action=content_types
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'content_types') {
    try {
        $contentTypes = $db->fetchAll(
            "SELECT DISTINCT content_type FROM cultural_content ORDER BY content_type"
        );
        
        $typeList = array_map(function($item) {
            return $item['content_type'];
        }, $contentTypes);
        
        Response::success($typeList);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Créer du nouveau contenu culturel (admin)
 * POST /api/library.php?action=create
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['language_id']) || empty($data['title']) || empty($data['content'])) {
        Response::error('language_id, title et content requis', 400);
    }
    
    try {
        $db->execute(
            "INSERT INTO cultural_content (language_id, title, content, content_type, category, region, media_url, author) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['language_id'],
                $data['title'],
                $data['content'],
                $data['content_type'] ?? 'article',
                $data['category'] ?? null,
                $data['region'] ?? null,
                $data['media_url'] ?? null,
                $data['author'] ?? null
            ]
        );
        
        $contentId = $db->lastInsertId('cultural_content_id_seq');
        
        $content = $db->fetchOne(
            "SELECT * FROM cultural_content WHERE id = ?",
            [$contentId]
        );
        
        Response::success($content, 'Contenu créé', 201);
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Mettre à jour du contenu culturel (admin)
 * PUT /api/library.php?action=update&id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'PUT' && isset($_GET['action']) && $_GET['action'] === 'update') {
    $id = $_GET['id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$id) {
        Response::error('id requis', 400);
    }
    
    try {
        $updates = [];
        $params = [];
        
        if (isset($data['title'])) {
            $updates[] = "title = ?";
            $params[] = $data['title'];
        }
        
        if (isset($data['content'])) {
            $updates[] = "content = ?";
            $params[] = $data['content'];
        }
        
        if (isset($data['content_type'])) {
            $updates[] = "content_type = ?";
            $params[] = $data['content_type'];
        }
        
        if (isset($data['category'])) {
            $updates[] = "category = ?";
            $params[] = $data['category'];
        }
        
        if (isset($data['media_url'])) {
            $updates[] = "media_url = ?";
            $params[] = $data['media_url'];
        }
        
        if (empty($updates)) {
            Response::error('Aucune donnée à mettre à jour', 400);
        }
        
        $params[] = $id;
        $sql = "UPDATE cultural_content SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $db->execute($sql, $params);
        
        $content = $db->fetchOne(
            "SELECT * FROM cultural_content WHERE id = ?",
            [$id]
        );
        
        Response::success($content, 'Contenu mis à jour');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

/**
 * Supprimer du contenu culturel (admin)
 * DELETE /api/library.php?action=delete&id=X
 */
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        Response::error('id requis', 400);
    }
    
    try {
        $db->execute("DELETE FROM cultural_content WHERE id = ?", [$id]);
        
        Response::success(null, 'Contenu supprimé');
        
    } catch (Exception $e) {
        Response::serverError('Erreur: ' . $e->getMessage());
    }
}

Response::notFound('Endpoint non trouvé');
?>
