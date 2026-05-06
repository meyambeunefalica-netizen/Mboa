-- ============================================
-- Mboa Database Schema
-- Compatible avec MySQL et PostgreSQL
-- ============================================

-- ============================================
-- TABLES D'UTILISATEURS
-- ============================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    avatar_url VARCHAR(500),
    level VARCHAR(50) DEFAULT 'Beginner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- ============================================
-- TABLES DE LANGUES
-- ============================================

CREATE TABLE languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(10) UNIQUE NOT NULL, -- ex: 'bul', 'bet', 'bam'
    region VARCHAR(100),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- PROGRESSION UTILISATEUR PAR LANGUE
-- ============================================

CREATE TABLE user_language_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    language_id INT NOT NULL,
    proficiency_level VARCHAR(50) DEFAULT 'Beginner',
    xp_points INT DEFAULT 0,
    lessons_completed INT DEFAULT 0,
    streak_days INT DEFAULT 0,
    last_practiced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_language (user_id, language_id)
);

-- ============================================
-- TABLES DE COURS
-- ============================================

CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    language_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    difficulty_level VARCHAR(50) DEFAULT 'Beginner',
    total_lessons INT DEFAULT 0,
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
);

-- ============================================
-- LEÇONS
-- ============================================

CREATE TABLE lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    lesson_order INT NOT NULL,
    content TEXT,
    audio_url VARCHAR(500),
    video_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ============================================
-- PROGRESSION DES LEÇONS
-- ============================================

CREATE TABLE lesson_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    score INT,
    completed_at TIMESTAMP,
    attempts INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_lesson (user_id, lesson_id)
);

-- ============================================
-- VOCABULAIRE
-- ============================================

CREATE TABLE vocabulary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    language_id INT NOT NULL,
    word VARCHAR(255) NOT NULL,
    translation VARCHAR(255) NOT NULL,
    phonetic VARCHAR(255),
    example_sentence TEXT,
    audio_url VARCHAR(500),
    category VARCHAR(100),
    difficulty_level VARCHAR(50) DEFAULT 'Beginner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
);

-- ============================================
-- VOCABULAIRE UTILISATEUR
-- ============================================

CREATE TABLE user_vocabulary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vocabulary_id INT NOT NULL,
    mastery_level INT DEFAULT 0, -- 0-5
    last_reviewed_at TIMESTAMP,
    next_review_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vocabulary_id) REFERENCES vocabulary(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_vocab (user_id, vocabulary_id)
);

-- ============================================
-- COMMUNAUTÉ - CHANNELS
-- ============================================

CREATE TABLE channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    language_id INT,
    description TEXT,
    is_public BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- COMMUNAUTÉ - MESSAGES
-- ============================================

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    message_type VARCHAR(50) DEFAULT 'text', -- text, image, audio
    attachment_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- COMMUNAUTÉ - MEMBRES DE CHANNEL
-- ============================================

CREATE TABLE channel_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'member', -- member, admin, moderator
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_channel_member (channel_id, user_id)
);

-- ============================================
-- AI TUTOR - SESSIONS
-- ============================================

CREATE TABLE ai_tutor_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    language_id INT NOT NULL,
    session_type VARCHAR(50) DEFAULT 'conversation', -- conversation, grammar, vocabulary
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
);

-- ============================================
-- AI TUTOR - MESSAGES
-- ============================================

CREATE TABLE ai_tutor_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    sender VARCHAR(50) NOT NULL, -- user, ai
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES ai_tutor_sessions(id) ON DELETE CASCADE
);

-- ============================================
-- LIVE PRACTICE - SESSIONS
-- ============================================

CREATE TABLE live_practice_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host_user_id INT NOT NULL,
    language_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    max_participants INT DEFAULT 10,
    scheduled_at TIMESTAMP,
    started_at TIMESTAMP,
    ended_at TIMESTAMP,
    status VARCHAR(50) DEFAULT 'scheduled', -- scheduled, live, completed, cancelled
    FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
);

-- ============================================
-- LIVE PRACTICE - PARTICIPANTS
-- ============================================

CREATE TABLE live_practice_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES live_practice_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_participant (session_id, user_id)
);

-- ============================================
-- BIBLIOTHÈQUE - CONTENU CULTUREL
-- ============================================

CREATE TABLE cultural_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    language_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    content_type VARCHAR(50) DEFAULT 'article', -- article, video, audio, image
    category VARCHAR(100),
    region VARCHAR(100),
    media_url VARCHAR(500),
    author VARCHAR(255),
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
);

-- ============================================
-- OBJECTIFS QUOTIDIENS
-- ============================================

CREATE TABLE daily_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_minutes INT DEFAULT 30,
    target_lessons INT DEFAULT 1,
    date DATE NOT NULL,
    completed_minutes INT DEFAULT 0,
    completed_lessons INT DEFAULT 0,
    is_achieved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, date)
);

-- ============================================
-- DONNÉES INITIALES
-- ============================================

-- Insérer des langues
INSERT INTO languages (name, code, region, description) VALUES
('Bulu', 'bul', 'South Region', 'Bantu language spoken by the Bulu people of Cameroon'),
('Beti', 'bet', 'Center Region', 'Bantu language spoken by the Beti people'),
('Bamileke', 'bam', 'West Region', 'Grassfields Bantu language spoken by the Bamileke people'),
('Ewondo', 'ewn', 'Center Region', 'Bantu language spoken in the Centre Region'),
('Douala', 'dua', 'Littoral Region', 'Bantu language spoken by the Douala people');

-- Insérer un utilisateur de test (mot de passe: password123)
INSERT INTO users (email, password_hash, first_name, last_name, level) VALUES
('test@lingocameroon.com', '$2y$10$PZgNKOOkrqH/PXNR7.KUuO/WfQr7FhBzA8xjtcgC5KCbid/zrySeK', 'Mbolo', 'Student', 'Intermediate');


-- Insérer du vocabulaire Bulu
INSERT INTO vocabulary (language_id, word, translation, phonetic, example_sentence, category, difficulty_level) VALUES
(1, 'Mbolo', 'Hello / Greetings', 'MBO-lo', 'Mbolo! Ye o yé mvaé?', 'Greetings', 'Beginner'),
(1, 'Ambe', 'Who', 'AM-be', 'Ambe a yé?', 'Pronouns', 'Beginner'),
(1, 'Akiba', 'Thank you', 'a-KI-ba', 'Akiba mbamba!', 'Greetings', 'Beginner'),
(1, 'Mvaé', 'Good / Well', 'M-VAE', 'Me yé mvaé', 'Adjectives', 'Beginner'),
(1, 'Nlem', 'Heart / Mind', 'N-LEM', 'Nlem nga be', 'Body', 'Beginner'),
(1, 'Bana', 'Children', 'BA-na', 'Bana ba yé mvaé', 'Family', 'Beginner');

-- Insérer du vocabulaire Beti
INSERT INTO vocabulary (language_id, word, translation, phonetic, example_sentence, category, difficulty_level) VALUES
(2, 'Mbombolo', 'Hello', 'MBOM-bo-lo', 'Mbombolo, nde?', 'Greetings', 'Beginner'),
(2, 'Ane', 'Mother', 'A-NE', 'Ane a yé mvaé', 'Family', 'Beginner'),
(2, 'Ese', 'House', 'E-SE', 'Ese nga be', 'Places', 'Beginner');

-- Insérer du contenu culturel
INSERT INTO cultural_content (language_id, title, content, content_type, category, region, author) VALUES
(1, 'The Legend of the Bamoun King', 'The Bamoun Kingdom, located in the Western Grassfields of Cameroon, has a rich history dating back to the 14th century. The Fon (king) of Bamoun is not only a political leader but also a spiritual figure who embodies the wisdom and traditions of the people. The famous King Njoya invented the Bamoun script, one of the few indigenous writing systems in Africa.', 'article', 'History', 'West Region', 'Mboa Team'),
(3, 'Traditions of the West', 'The Bamileke people are renowned for their intricate social structures and the breathtaking Fon''s palaces. The Ndop cloth, with its symbolic geometric patterns, tells stories of lineage, status, and spiritual beliefs. The Lali dance, performed during important ceremonies, connects the living with their ancestors through rhythm and movement.', 'article', 'Culture', 'Western Grassfields', 'Mboa Team'),
(5, 'Littoral Stories: The Ngondo Festival', 'The Ngondo festival is an annual celebration of the Sawa people along the Wouri River in Douala. It honors the spirits of the water and celebrates the maritime heritage of the coastal communities. The festival features traditional music, dance, and the famous Makossa rhythm that has influenced African music worldwide.', 'article', 'Festival', 'Littoral Region', 'Mboa Team'),
(4, 'Northern History: The Mandara Kingdom', 'From the Mandara Mountains to the Sahel plains, the northern regions of Cameroon hold ancient civilizations that shaped the cultural landscape. The Musgum people built remarkable domed clay structures that remain architectural marvels today.', 'article', 'History', 'Far North', 'Mboa Team'),
(2, 'Beti Oral Traditions', 'The Beti people of the Center Region have a rich tradition of oral storytelling. Tales of the tortoise, the leopard, and the elephant teach moral lessons and preserve the wisdom of generations. These stories are traditionally told around the fire in the evening.', 'article', 'Storytelling', 'Center Region', 'Mboa Team');
