<?php
/**
 * Helper Mistral AI - Agent Mboa IA
 * Intégration avec l'API Mistral pour le tutor IA
 */

class MistralAI {
    private $apiKey;
    private $apiUrl = 'https://api.mistral.ai/v1/chat/completions';
    private $model = 'mistral-medium';
    
    public function __construct() {
        $this->apiKey = getenv('MISTRAL_API_KEY') ?: 'LdHmWp1zphUfEVoJNq1GKhLqMQlirWjy';
    }
    
    /**
     * Générer une réponse de l'AI pour le tutor
     * @param string $userMessage - Message de l'utilisateur
     * @param array $conversationHistory - Historique de conversation
     * @param string $language - Langue d'apprentissage (ex: Bulu, Ewondo, etc.)
     * @return string - Réponse de l'AI
     */
    public function generateTutorResponse($userMessage, $conversationHistory = [], $language = 'Bulu') {
        $systemPrompt = $this->buildSystemPrompt($language);
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];
        
        // Ajouter l'historique de conversation
        foreach ($conversationHistory as $msg) {
            $role = $msg['sender'] === 'ai' ? 'assistant' : 'user';
            $messages[] = [
                'role' => $role,
                'content' => $msg['content']
            ];
        }
        
        // Ajouter le message actuel
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];
        
        return $this->callAPI($messages);
    }
    
    /**
     * Construire le prompt système pour le contexte camerounais
     */
    private function buildSystemPrompt($language) {
        return "Tu es l'Agent Mboa IA, un tuteur IA spécialisé dans l'apprentissage des langues camerounaises. 
Ta mission est d'aider les utilisateurs à apprendre le {$language} de manière interactive et engageante.

CONTEXTE CULTUREL:
- Le {$language} est une langue bantoue parlée au Cameroun
- Respecte la culture camerounaise dans tes réponses
- Utilise des expressions locales quand approprié (ex: Mbolo pour bonjour)
- Sois encourageant et patient

DIRECTIVES PÉDAGOGIQUES:
1. Corrige gentiment les erreurs de grammaire ou de vocabulaire
2. Explique les règles grammaticales simplement
3. Propose des exercices pratiques
4. Donne des exemples de phrases utiles
5. Intègre des éléments culturels camerounais
6. Réponds en français mais avec des exemples en {$language}
7. Sois concis et direct (max 2-3 phrases par réponse)
8. Utilise un ton amical et motivant

FORMAT DE RÉPONSE:
- Commence par une encouragement ou correction
- Donne l'explication ou l'exemple
- Termine par une question pour continuer la conversation

Exemple de réponse:
Excellent ! En Bulu, on dit Mbolo pour dire bonjour. Essaie de me dire comment tu vas aujourd'hui en Bulu !";

    }
    
    /**
     * Appeler l'API Mistral
     */
    private function callAPI($messages) {
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 500
        ];
        
        $options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n" .
                            "Authorization: Bearer " . $this->apiKey . "\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'timeout' => 30
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($this->apiUrl, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            error_log('Mistral API Error: ' . ($error['message'] ?? 'Unknown error'));
            return $this->getFallbackResponse();
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
        
        error_log('Mistral API Response Error: ' . $response);
        return $this->getFallbackResponse();
    }
    
    /**
     * Test avec délai pour vérifier l'indicateur de typing
     */
    public function testWithDelay($message) {
        // Simuler un délai de 2 secondes pour voir l'indicateur de typing
        sleep(2);
        return "Test: " . $message;
    }
    
    /**
     * Réponse de secours si l'API échoue
     */
    private function getFallbackResponse() {
        $responses = [
            "Mvaé mbamba! Continue à pratiquer, tu progresses bien !",
            "Excellent effort ! Essaie encore avec cette expression.",
            "Tu es sur la bonne voie ! La pratique rend parfait.",
            "N'abandonne pas ! Chaque erreur est une opportunité d'apprendre.",
            "Bien ! Essayons une autre phrase ensemble."
        ];
        
        return $responses[array_rand($responses)];
    }
}
?>
