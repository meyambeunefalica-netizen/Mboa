# Intégration LiveKit - LingoCameroon

Documentation pour l'intégration de LiveKit dans LingoCameroon pour les appels audio/vidéo en temps réel.

## Vue d'ensemble

LiveKit est utilisé pour gérer les visioconférences en temps réel dans l'application. L'intégration permet :
- Audio/vidéo en temps réel via WebRTC
- Support multi-participants
- Gestion des rooms et participants

## Architecture

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   Client    │         │   Backend    │         │  LiveKit    │
│  (HTML/JS)  │◄────────┤     PHP      ├────────►│   Server    │
│             │  Token   │              │  JWT     │  (Local)   │
└──────┬──────┘         └──────────────┘         └──────┬──────┘
       │                                                │
       └────────────────────────────────────────────────┘
                    Connexion WebRTC (Audio/Video)
```

## 1. Serveur LiveKit

### Configuration (`livekit.yaml`)

Le fichier de configuration est déjà créé à la racine du projet.

- **Port**: 7880
- **API Key**: devkey
- **API Secret**: secret
- **Ports RTC**: 50000-60000

### Démarrage du serveur

```bash
./start_livekit.sh
```

Le serveur démarre sur `ws://localhost:7880` en développement.

## 2. Backend (PHP)

### Configuration (`backend/config/config.php`)

Les constantes LiveKit ont été ajoutées :

```php
define('LIVEKIT_URL', 'ws://localhost:7880');
define('LIVEKIT_API_KEY', 'devkey');
define('LIVEKIT_API_SECRET', 'secret');
```

### API Endpoints (`backend/api/livekit.php`)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/livekit.php?action=token` | Génère un token JWT pour rejoindre une room |
| POST | `/api/livekit.php?action=create_room` | Crée une nouvelle room |
| GET | `/api/livekit.php?action=rooms` | Liste les rooms disponibles |
| POST | `/api/livekit.php?action=join_room&room_id=X` | Rejoindre une room |
| POST | `/api/livekit.php?action=leave_room&room_id=X` | Quitter une room |

### Exemple d'utilisation

```bash
# Obtenir un token
curl -X POST http://localhost:8000/backend/api/livekit.php?action=token \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"room_name": "session_123", "participant_name": "User"}'
```

## 3. Frontend (HTML/JavaScript)

### Dépendances

Le SDK LiveKit est chargé dynamiquement depuis CDN dans `js/livekit-client.js`.

### Client LiveKit (`js/livekit-client.js`)

Classe JavaScript pour gérer la connexion LiveKit :

```javascript
// Connecter à une room
await liveKitClient.connect(roomName, token, serverUrl);

// Activer/désactiver le microphone
await liveKitClient.toggleMicrophone(true);

// Activer/désactiver la caméra
await liveKitClient.toggleCamera(true);

// Envoyer des données
await liveKitClient.sendData({type: 'chat', message: 'Hello'});

// Déconnecter
await liveKitClient.disconnect();
```

### Intégration dans `live-practice.html`

La page Live Practice a été mise à jour pour intégrer LiveKit :

- Boutons pour activer/désactiver le microphone et la caméra
- Affichage de la vidéo locale et des vidéos distantes
- Gestion automatique de la connexion lors de l'entrée dans une session

## 4. Base de données

### Tables ajoutées (`backend/database/schema_pgsql.sql`)

```sql
-- Rooms LiveKit
CREATE TABLE livekit_rooms (
    id SERIAL PRIMARY KEY,
    room_name VARCHAR(255) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    host_user_id INT NOT NULL REFERENCES users(id),
    language_id INT REFERENCES languages(id),
    max_participants INT DEFAULT 10,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Participants aux rooms
CREATE TABLE livekit_room_participants (
    id SERIAL PRIMARY KEY,
    room_id INT NOT NULL REFERENCES livekit_rooms(id),
    user_id INT NOT NULL REFERENCES users(id),
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP,
    UNIQUE (room_id, user_id)
);
```

## 5. Flux de communication

```
┌─────────────────────────────────────────────────────────────┐
│                     Création d'un meeting                    │
└─────────────────────────────────────────────────────────────┘

1. User: Crée une session via Live Practice
   ↓
2. Backend: Crée la session en base de données
   ↓
3. User: Clique sur "Join Session"
   ↓
4. Frontend: Demande un token LiveKit via /api/livekit.php?action=token
   ↓
5. Backend: Génère un token JWT signé avec LIVEKIT_API_SECRET
   ↓
6. Frontend: Connecte à LiveKit avec le token
   ↓
7. WebRTC: Connexion P2P établie (audio/video)
```

## 6. Démarrage complet

Pour démarrer l'application complète :

```bash
# 1. Démarrer le serveur LiveKit
./start_livekit.sh

# 2. Démarrer le backend PHP
cd backend
php -S localhost:8000

# 3. Ouvrir live-practice.html dans le navigateur
```

## 7. Configuration pour la production

Pour le déploiement en production, utiliser **LiveKit Cloud** (gratuit) :

1. Créer un compte sur [livekit.io/cloud](https://livekit.io/cloud)
2. Récupérer l'URL (`wss://project-xxx.livekit.cloud`) et les clés API
3. Mettre à jour `backend/config/config.php` :

```php
define('LIVEKIT_URL', 'wss://project-xxx.livekit.cloud');
define('LIVEKIT_API_KEY', 'your_production_api_key');
define('LIVEKIT_API_SECRET', 'your_production_api_secret');
```

## 8. Résolution de problèmes

### Problème de connexion WebRTC

En développement local, `use_external_ip: false` dans `livekit.yaml`.
En production, configurer correctement le serveur TURN/STUN ou utiliser LiveKit Cloud.

### Token invalide

Vérifier que `LIVEKIT_API_KEY` et `LIVEKIT_API_SECRET` correspondent entre le serveur LiveKit et le backend.

### Permissions

Assurez-vous que le binaire LiveKit a les permissions d'exécution :

```bash
chmod +x livekit_1.9.0_linux_amd64
```

### Ports

Vérifiez que les ports suivants sont ouverts :
- 7880 (WebSocket)
- 50000-60000 (RTC/WebRTC)

## 9. Logs

### Voir les logs LiveKit

```bash
tail -f livekit.log
```

## 10. Sécurité

**IMPORTANT**: En production, changez les clés API par défaut :

```yaml
# livekit.yaml
keys:
  your_secure_key: your_secure_secret
```

```php
// backend/config/config.php
define('LIVEKIT_API_KEY', 'your_secure_key');
define('LIVEKIT_API_SECRET', 'your_secure_secret');
```
