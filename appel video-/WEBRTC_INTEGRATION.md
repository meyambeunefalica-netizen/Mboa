# LingoCameroon — Visioconférence WebRTC (sans LiveKit)

## Architecture

```
Navigateur A ──┐                        ┌── Navigateur B
               │   SDP offer/answer     │
               ├──► PHP webrtc-signal ◄─┤  (signalisation uniquement)
               │   ICE candidates       │
               │                        │
               └────────────────────────┘
                   Flux P2P direct
                 (audio / vidéo / chat)
```

La signalisation passe par **long-polling HTTP** sur PostgreSQL.
Une fois la connexion établie, **aucun serveur intermédiaire** ne touche le flux audio/vidéo.

---

## Fichiers à placer dans votre projet

| Fichier source (ce dossier) | Destination dans le projet |
|---|---|
| `sql/webrtc_schema.sql` | Exécuter sur PostgreSQL (une fois) |
| `backend/config/config.php` | `backend/config/config.php` |
| `backend/api/webrtc-signal.php` | `backend/api/webrtc-signal.php` |
| `backend/api/live-practice.php` | `backend/api/live-practice.php` |
| `js/webrtc-client.js` | `js/webrtc-client.js` |
| `live-practice.html` | `live-practice.html` |

> **Supprimer** : `livekit.php`, `js/livekit-client.js`, `livekit.yaml`, `start_livekit.sh`

---

## Mise en place (développement)

### 1. Base de données
```sql
-- Connectez-vous à PostgreSQL et exécutez :
\c lingocameroon
\i sql/webrtc_schema.sql
```

### 2. PHP (configuration)
Éditez `backend/config/config.php` si nécessaire :
```php
define('PGSQL_HOST',     '/var/run/postgresql');  // ou 'localhost'
define('PGSQL_DATABASE', 'lingocameroon');
define('PGSQL_USERNAME', 'postgres');
define('PGSQL_PASSWORD', '');
```

### 3. Lancer le serveur de développement
```bash
cd /chemin/vers/projet
php -S localhost:8000
```

### 4. Tester
- Ouvrir `http://localhost:8000/live-practice.html` dans deux onglets
- Se connecter avec deux comptes différents
- Créer une session dans le premier onglet, la rejoindre dans le second
- L'appel WebRTC s'établit automatiquement

---

## Déploiement en production

### Prérequis serveur
- PHP 8.0+, PostgreSQL 13+
- HTTPS **obligatoire** (WebRTC refuse getUserMedia en HTTP non-local)
- `set_time_limit` > 30s (pour le long-polling à 20s)

### php.ini recommandé
```ini
max_execution_time = 30
max_input_time = 30
```

### HTTPS avec Let's Encrypt (exemple Nginx)
```nginx
server {
    listen 443 ssl;
    server_name votredomaine.com;
    ssl_certificate     /etc/letsencrypt/live/votredomaine.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/votredomaine.com/privkey.pem;

    root /var/www/lingocameroon;
    index index.html;

    location /backend/api/ {
        try_files $uri $uri/ =404;
        # Augmenter le timeout pour le long-polling
        fastcgi_read_timeout 30;
    }
}
```

### TURN server (si NAT symétrique)
En production derrière des NAT stricts, ajoutez Coturn :
```bash
sudo apt install coturn
```
Puis mettez à jour `_iceServers` dans `js/webrtc-client.js` :
```js
this._iceServers = [
    { urls: 'stun:stun.l.google.com:19302' },
    {
        urls:       'turn:votre-ip:3478',
        username:   'lingocameroon',
        credential: 'motdepasse',
    },
];
```

### Nettoyage automatique des signaux (cron)
```bash
# Ajouter dans crontab -e :
*/5 * * * * psql -U postgres -d lingocameroon -c "DELETE FROM webrtc_signals WHERE created_at < NOW() - INTERVAL '2 minutes';"
```

---

## API Reference

### `POST /api/webrtc-signal.php?action=send`
Envoyer un signal WebRTC à un ou plusieurs pairs.
```json
{
  "session_id": 42,
  "to_user_id": 7,      // optionnel — null = broadcast
  "type": "offer",      // offer | answer | ice-candidate | leave
  "payload": { ... }    // SDP ou ICE candidate
}
```

### `GET /api/webrtc-signal.php?action=poll&session_id=42&since_id=0`
Long-poll : attend jusqu'à 20s et retourne les nouveaux signaux.
```json
{
  "signals": [
    { "id": 5, "from_user_id": 7, "signal_type": "offer", "payload": {...} }
  ],
  "since_id": 5
}
```

### `GET /api/webrtc-signal.php?action=peers&session_id=42`
Retourne les participants actifs (hors soi-même).

### `POST /api/webrtc-signal.php?action=ping&session_id=42`
Heartbeat toutes les 15s pour maintenir la présence.

---

## Dépendances JavaScript
**Zéro dépendance externe.** `webrtc-client.js` utilise uniquement :
- `RTCPeerConnection` (natif)
- `navigator.mediaDevices.getUserMedia` (natif)
- `fetch` (natif)

---

## Limites connues et solutions

| Situation | Solution |
|---|---|
| NAT symétrique (rare, ~15% du web) | Ajouter un TURN server (Coturn) |
| Plus de ~8 participants | Passer à une topologie SFU (ex. mediasoup auto-hébergé) |
| Navigateur IE/Edge legacy | WebRTC non supporté — afficher un message |
| Long-polling trop lent | Remplacer par WebSocket PHP (Ratchet/Swoole) sans changer le client |
