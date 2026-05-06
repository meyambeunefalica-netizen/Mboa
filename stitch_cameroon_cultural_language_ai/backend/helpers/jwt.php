<?php
/**
 * Helper pour la gestion des tokens JWT
 * Utilisation simple sans dépendance externe
 */

class JWT {
    private static $secret = JWT_SECRET;
    private static $algorithm = 'HS256';

    /**
     * Encoder un token JWT
     */
    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algorithm]);
        $payload['iat'] = time();
        $payload['exp'] = time() + SESSION_LIFETIME;
        
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Décoder un token JWT
     */
    public static function decode($token) {
        $tokenParts = explode('.', $token);
        
        if (count($tokenParts) !== 3) {
            return false;
        }
        
        list($header, $payload, $signature) = $tokenParts;
        
        $validSignature = hash_hmac('sha256', $header . "." . $payload, self::$secret, true);
        $base64UrlValidSignature = self::base64UrlEncode($validSignature);
        
        if (!hash_equals($signature, $base64UrlValidSignature)) {
            return false;
        }
        
        $decodedPayload = json_decode(self::base64UrlDecode($payload), true);
        
        // Vérifier l'expiration
        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return false;
        }
        
        return $decodedPayload;
    }

    /**
     * Encoder en base64 URL-safe
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Décoder depuis base64 URL-safe
     */
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Vérifier si un token est valide
     */
    public static function verify($token) {
        return self::decode($token) !== false;
    }

    /**
     * Extraire l'ID utilisateur d'un token
     */
    public static function getUserId($token) {
        $payload = self::decode($token);
        return $payload ? $payload['user_id'] : null;
    }

    /**
     * Encoder un token JWT avec un secret personnalisé (pour LiveKit)
     */
    public static function encodeCustom($payload, $customSecret) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $customSecret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
}
?>
