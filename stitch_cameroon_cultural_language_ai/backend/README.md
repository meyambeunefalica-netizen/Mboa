# Mboa Backend API

Backend PHP complet pour l'application Mboa avec support MySQL et PostgreSQL.

## Structure du projet

```
backend/
├── config/
│   └── config.php          # Configuration de la base de données
├── database/
│   └── schema.sql          # Schéma de la base de données
├── helpers/
│   ├── response.php        # Helper pour les réponses JSON
│   └── jwt.php             # Helper pour la gestion des tokens JWT
├── api/
│   ├── auth.php            # API d'authentification
│   ├── users.php           # API utilisateurs
│   ├── courses.php         # API cours et leçons
│   ├── community.php       # API communauté
│   ├── ai-tutor.php        # API AI Tutor
│   ├── live-practice.php   # API Live Practice
│   └── library.php         # API bibliothèque
└── README.md
```

## Installation

### 1. Configuration de la base de données

Ouvrez `config/config.php` et configurez votre connexion :

```php
// Pour MySQL
define('DB_TYPE', 'mysql');
define('MYSQL_HOST', 'localhost');
define('MYSQL_PORT', '3306');
define('MYSQL_DATABASE', 'lingocameroon');
define('MYSQL_USERNAME', 'root');
define('MYSQL_PASSWORD', '');

// Pour PostgreSQL
define('DB_TYPE', 'postgresql');
define('PGSQL_HOST', 'localhost');
define('PGSQL_PORT', '5432');
define('PGSQL_DATABASE', 'lingocameroon');
define('PGSQL_USERNAME', 'postgres');
define('PGSQL_PASSWORD', '');
```

### 2. Création de la base de données

Importez le schéma SQL :

```bash
# Pour MySQL
mysql -u root -p lingocameroon < database/schema.sql

# Pour PostgreSQL
psql -U postgres -d lingocameroon -f database/schema.sql
```

### 3. Configuration du serveur web

#### Apache

Créez un fichier `.htaccess` dans le dossier `backend/` :

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

#### Nginx

Ajoutez cette configuration à votre bloc server :

```nginx
location /backend {
    alias /path/to/backend;
    try_files $uri $uri/ /backend/api/index.php?$query_string;
}
```

### 4. Démarrage avec PHP built-in server (développement)

```bash
cd backend
php -S localhost:8000
```

## API Endpoints

### Authentification (`/api/auth.php`)

- `POST /api/auth.php?action=register` - Inscription
  ```json
  {
    "email": "user@example.com",
    "password": "password123",
    "first_name": "John",
    "last_name": "Doe"
  }
  ```

- `POST /api/auth.php?action=login` - Connexion
  ```json
  {
    "email": "user@example.com",
    "password": "password123"
  }
  ```

- `GET /api/auth.php?action=verify` - Vérifier le token
  - Headers: `Authorization: Bearer <token>`

- `POST /api/auth.php?action=logout` - Déconnexion

### Utilisateurs (`/api/users.php`)

- `GET /api/users.php` - Récupérer le profil utilisateur
  - Headers: `Authorization: Bearer <token>`

- `PUT /api/users.php?action=update` - Mettre à jour le profil
  ```json
  {
    "first_name": "John",
    "last_name": "Doe",
    "avatar_url": "https://example.com/avatar.jpg"
  }
  ```

- `POST /api/users.php?action=progress` - Mettre à jour la progression
  ```json
  {
    "language_id": 1,
    "xp_points": 50,
    "lessons_completed": 1,
    "minutes_spent": 15
  }
  ```

- `GET /api/users.php?action=languages` - Récupérer les langues disponibles

### Cours (`/api/courses.php`)

- `GET /api/courses.php` - Récupérer tous les cours
- `GET /api/courses.php?action=lessons&course_id=X` - Récupérer les leçons d'un cours
- `POST /api/courses.php?action=complete&lesson_id=X` - Marquer une leçon comme terminée
- `GET /api/courses.php?action=vocabulary&language_id=X` - Récupérer le vocabulaire
- `POST /api/courses.php?action=vocabulary_mastery` - Mettre à jour la maîtrise d'un mot

### Communauté (`/api/community.php`)

- `GET /api/community.php?action=channels` - Récupérer les channels
- `POST /api/community.php?action=join&channel_id=X` - Rejoindre un channel
- `POST /api/community.php?action=leave&channel_id=X` - Quitter un channel
- `GET /api/community.php?action=messages&channel_id=X` - Récupérer les messages
- `POST /api/community.php?action=send_message` - Envoyer un message
- `GET /api/community.php?action=members&channel_id=X` - Récupérer les membres

### AI Tutor (`/api/ai-tutor.php`)

- `POST /api/ai-tutor.php?action=create_session` - Créer une session
- `GET /api/ai-tutor.php?action=sessions` - Récupérer les sessions
- `GET /api/ai-tutor.php?action=messages&session_id=X` - Récupérer les messages
- `POST /api/ai-tutor.php?action=send_message&session_id=X` - Envoyer un message
- `POST /api/ai-tutor.php?action=end_session&session_id=X` - Terminer une session

### Live Practice (`/api/live-practice.php`)

- `POST /api/live-practice.php?action=create_session` - Créer une session
- `GET /api/live-practice.php?action=sessions` - Récupérer les sessions
- `POST /api/live-practice.php?action=join&session_id=X` - Rejoindre une session
- `POST /api/live-practice.php?action=leave&session_id=X` - Quitter une session
- `POST /api/live-practice.php?action=start&session_id=X` - Démarrer une session
- `POST /api/live-practice.php?action=end&session_id=X` - Terminer une session
- `GET /api/live-practice.php?action=participants&session_id=X` - Récupérer les participants

### Bibliothèque (`/api/library.php`)

- `GET /api/library.php` - Récupérer tout le contenu culturel
- `GET /api/library.php?action=detail&id=X` - Récupérer un contenu spécifique
- `GET /api/library.php?action=categories` - Récupérer les catégories
- `GET /api/library.php?action=content_types` - Récupérer les types de contenu
- `POST /api/library.php?action=create` - Créer du contenu (admin)
- `PUT /api/library.php?action=update&id=X` - Mettre à jour du contenu (admin)
- `DELETE /api/library.php?action=delete&id=X` - Supprimer du contenu (admin)

## Format des réponses

### Succès
```json
{
  "success": true,
  "message": "Success message",
  "data": { ... }
}
```

### Erreur
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... }
}
```

## Sécurité

- Les mots de passe sont hashés avec `password_hash()` (bcrypt)
- Les tokens JWT sont utilisés pour l'authentification
- Les requêtes SQL utilisent des prepared statements pour prévenir les injections SQL
- CORS est configuré pour autoriser les requêtes cross-origin

## Développement

### Variables d'environnement

Modifiez les constantes dans `config/config.php` :

```php
define('APP_ENV', 'development'); // 'development' ou 'production'
define('JWT_SECRET', 'votre_cle_secrete_ici');
define('SESSION_LIFETIME', 86400); // 24 heures
```

### Logs

Les erreurs sont loggées dans le fichier d'erreur PHP par défaut. Configurez `error_log` dans `php.ini` si nécessaire.

## Production

1. Changez `APP_ENV` à `'production'` dans `config/config.php`
2. Utilisez une clé JWT forte et unique
3. Configurez HTTPS
4. Limitez les requêtes CORS aux domaines autorisés
5. Activez la validation des entrées côté serveur
6. Utilisez un serveur web de production (Apache/Nginx)
7. Configurez la base de données avec des credentials sécurisés

## Support

Pour toute question ou problème, contactez l'équipe de développement.
