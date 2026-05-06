# Intégration Mistral AI - Agent Mboa IA

## Vue d'ensemble

L'Agent Mboa IA est un tuteur IA intelligent intégré à la plateforme Mboa, utilisant l'API Mistral pour fournir des réponses contextuelles et culturellement adaptées pour l'apprentissage des langues camerounaises.

## Configuration

### API Key

L'API key Mistral est configurée dans le fichier `backend/helpers/mistral.php` :

```php
private $apiKey = getenv('MISTRAL_API_KEY') ?: 'LdHmWp1zphUfEVoJNq1GKhLqMQlirWjy';
```

Pour une configuration plus sécurisée en production, définissez la variable d'environnement `MISTRAL_API_KEY` au lieu de la hardcoder.

### Modèle Utilisé

- **Modèle**: `mistral-medium`
- **Temperature**: 0.7 (équilibré entre créativité et cohérence)
- **Max tokens**: 500 (réponses concises)

## Structure des Fichiers

```
backend/
├── helpers/
│   └── mistral.php          # Classe MistralAI
└── api/
    └── ai-tutor.php         # API endpoint modifié pour utiliser Mistral
```

## Fonctionnalités

### 1. Classe MistralAI (`backend/helpers/mistral.php`)

#### Méthode principale: `generateTutorResponse()`

Génère une réponse de l'IA en tenant compte de:
- Le message de l'utilisateur
- L'historique de conversation
- La langue d'apprentissage (Bulu, Ewondo, etc.)

```php
$mistral = new MistralAI();
$response = $mistral->generateTutorResponse(
    $userMessage, 
    $conversationHistory, 
    $languageName
);
```

#### Prompt Système

Le prompt système est conçu pour:
- Être un tuteur spécialisé dans les langues camerounaises
- Respecter le contexte culturel
- Corriger gentiment les erreurs
- Proposer des exercices pratiques
- Intégrer des éléments culturels
- Être concis (2-3 phrases par réponse)

### 2. API AI Tutor (`backend/api/ai-tutor.php`)

#### Endpoint: `POST /api/ai-tutor.php?action=send_message&session_id=X`

Flux de traitement:
1. Vérifie l'authentification JWT
2. Récupère la session et la langue
3. Insère le message utilisateur
4. Récupère l'historique de conversation
5. Appelle Mistral AI pour générer la réponse
6. Insère la réponse de l'IA
7. Retourne tous les messages

## Personnalisation

### Modifier le Prompt Système

Éditez la méthode `buildSystemPrompt()` dans `backend/helpers/mistral.php` pour ajuster:
- Le ton de l'IA
- Les directives pédagogiques
- Le format des réponses
- Les exemples de réponses

### Changer de Modèle

Modifiez la propriété `$model` dans le constructeur:

```php
private $model = 'mistral-small';  // Plus rapide
// ou
private $model = 'mistral-large';  // Plus intelligent
```

## Gestion des Erreurs

### Fallback Response

Si l'API Mistral échoue (timeout, erreur HTTP, etc.), le système retourne automatiquement une réponse de secours prédéfinie en français pour garantir que l'utilisateur ne soit pas bloqué.

### Logging

Les erreurs sont loggées dans le error log PHP pour le débogage.

## Sécurité

- L'API key est stockée côté serveur uniquement
- L'authentification JWT est requise pour toutes les requêtes
- Les requêtes API sont limitées à 30 secondes de timeout
- Validation des entrées utilisateur avant envoi à l'API

## Tests

Pour tester l'intégration:

1. Démarrez le serveur PHP
2. Connectez-vous à l'application
3. Naviguez vers "Agent Mboa IA"
4. Sélectionnez une langue
5. Cliquez sur "Démarrer la session"
6. Envoyez un message et vérifiez la réponse

## Dépannage

### Problème: Réponses vides ou erreurs

1. Vérifiez que l'API key est valide
2. Consultez les logs PHP pour les erreurs
3. Vérifiez la connexion internet du serveur
4. Testez l'API Mistral directement avec curl

### Problème: Réponses lentes

1. Le modèle `mistral-medium` peut prendre 2-5 secondes
2. Considérez d'utiliser `mistral-small` pour des réponses plus rapides
3. Vérifiez la latence réseau

## Améliorations Futures

- [ ] Ajouter le support de la voix (TTS)
- [ ] Implémenter la détection de langue automatique
- [ ] Ajouter des exercices générés dynamiquement
- [ ] Intégrer la reconnaissance vocale (STT)
- [ ] Ajouter des quiz de vocabulaire
- [ ] Implémenter un système de progression basé sur les conversations

## Support

Pour toute question ou problème avec l'intégration Mistral, consultez:
- Documentation Mistral: https://docs.mistral.ai/
- API Reference: https://docs.mistral.ai/api/
