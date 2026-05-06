<?php
/**
 * Helper pour les réponses JSON standardisées
 */

class Response {
    /**
     * Réponse de succès
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    /**
     * Réponse d'erreur
     */
    public static function error($message = 'Error', $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        $response = [
            'success' => false,
            'message' => $message
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        echo json_encode($response);
        exit;
    }

    /**
     * Réponse non autorisée
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }

    /**
     * Réponse interdite
     */
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }

    /**
     * Réponse non trouvée
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }

    /**
     * Réponse erreur serveur
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
}
?>
