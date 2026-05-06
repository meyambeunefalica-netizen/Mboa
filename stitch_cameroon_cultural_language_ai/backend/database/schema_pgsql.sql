-- ============================================
-- Mboa Database Schema - PostgreSQL
-- ============================================

-- TABLES D'UTILISATEURS
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    avatar_url VARCHAR(500),
    level VARCHAR(50) DEFAULT 'Beginner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- TABLES DE LANGUES
CREATE TABLE languages (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(10) UNIQUE NOT NULL,
    region VARCHAR(100),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PROGRESSION UTILISATEUR PAR LANGUE
CREATE TABLE user_language_progress (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    language_id INT NOT NULL REFERENCES languages(id) ON DELETE CASCADE,
    proficiency_level VARCHAR(50) DEFAULT 'Beginner',
    xp_points INT DEFAULT 0,
    lessons_completed INT DEFAULT 0,
    streak_days INT DEFAULT 0,
    last_practiced_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, language_id)
);

CREATE TRIGGER update_ulp_updated_at BEFORE UPDATE ON user_language_progress
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- TABLES DE COURS
CREATE TABLE courses (
    id SERIAL PRIMARY KEY,
    language_id INT NOT NULL REFERENCES languages(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    difficulty_level VARCHAR(50) DEFAULT 'Beginner',
    total_lessons INT DEFAULT 0,
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_courses_updated_at BEFORE UPDATE ON courses
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- LECONS
CREATE TABLE lessons (
    id SERIAL PRIMARY KEY,
    course_id INT NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    lesson_order INT NOT NULL,
    content TEXT,
    audio_url VARCHAR(500),
    video_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PROGRESSION DES LECONS
CREATE TABLE lesson_progress (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    lesson_id INT NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
    is_completed BOOLEAN DEFAULT FALSE,
    score INT,
    completed_at TIMESTAMP,
    attempts INT DEFAULT 0,
    UNIQUE (user_id, lesson_id)
);

-- VOCABULAIRE
CREATE TABLE vocabulary (
    id SERIAL PRIMARY KEY,
    language_id INT NOT NULL REFERENCES languages(id) ON DELETE CASCADE,
    word VARCHAR(255) NOT NULL,
    translation VARCHAR(255) NOT NULL,
    phonetic VARCHAR(255),
    example_sentence TEXT,
    audio_url VARCHAR(500),
    category VARCHAR(100),
    difficulty_level VARCHAR(50) DEFAULT 'Beginner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- VOCABULAIRE UTILISATEUR
CREATE TABLE user_vocabulary (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    vocabulary_id INT NOT NULL REFERENCES vocabulary(id) ON DELETE CASCADE,
    mastery_level INT DEFAULT 0,
    last_reviewed_at TIMESTAMP,
    next_review_at TIMESTAMP,
    UNIQUE (user_id, vocabulary_id)
);

-- COMMUNAUTE - CHANNELS
CREATE TABLE channels (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    language_id INT REFERENCES languages(id) ON DELETE SET NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT TRUE,
    created_by INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- COMMUNAUTE - MESSAGES
CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    channel_id INT NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    message_type VARCHAR(50) DEFAULT 'text',
    attachment_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- COMMUNAUTE - MEMBRES DE CHANNEL
CREATE TABLE channel_members (
    id SERIAL PRIMARY KEY,
    channel_id INT NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(50) DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (channel_id, user_id)
);

-- AI TUTOR - SESSIONS
CREATE TABLE ai_tutor_sessions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    language_id INT NOT NULL REFERENCES languages(id) ON DELETE CASCADE,
    session_type VARCHAR(50) DEFAULT 'conversation',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP
);

-- AI TUTOR - MESSAGES
CREATE TABLE ai_tutor_messages (
    id SERIAL PRIMARY KEY,
    session_id INT NOT NULL REFERENCES ai_tutor_sessions(id) ON DELETE CASCADE,
    sender VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- LIVE PRACTICE - SESSIONS
CREATE TABLE live_practice_sessions (
    id SERIAL PRIMARY KEY,
    host_user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    language_id INT NOT NULL REFERENCES languages(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    max_participants INT DEFAULT 10,
    scheduled_at TIMESTAMP,
    started_at TIMESTAMP,
    ended_at TIMESTAMP,
    status VARCHAR(50) DEFAULT 'scheduled'
);

-- LIVE PRACTICE - PARTICIPANTS
CREATE TABLE live_practice_participants (
    id SERIAL PRIMARY KEY,
    session_id INT NOT NULL REFERENCES live_practice_sessions(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP,
    UNIQUE (session_id, user_id)
);

-- BIBLIOTHEQUE - CONTENU CULTUREL
CREATE TABLE cultural_content (
    id SERIAL PRIMARY KEY,
    language_id INT NOT NULL REFERENCES languages(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    content_type VARCHAR(50) DEFAULT 'article',
    category VARCHAR(100),
    region VARCHAR(100),
    media_url VARCHAR(500),
    author VARCHAR(255),
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- OBJECTIFS QUOTIDIENS
CREATE TABLE daily_goals (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    target_minutes INT DEFAULT 30,
    target_lessons INT DEFAULT 1,
    date DATE NOT NULL,
    completed_minutes INT DEFAULT 0,
    completed_lessons INT DEFAULT 0,
    is_achieved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, date)
);

-- ============================================
-- DONNEES INITIALES
-- ============================================

INSERT INTO languages (name, code, region, description) VALUES
('Bulu', 'bul', 'South Region', 'Bantu language spoken by the Bulu people of Cameroon'),
('Beti', 'bet', 'Center Region', 'Bantu language spoken by the Beti people'),
('Bamileke', 'bam', 'West Region', 'Grassfields Bantu language spoken by the Bamileke people'),
('Ewondo', 'ewn', 'Center Region', 'Bantu language spoken in the Centre Region'),
('Douala', 'dua', 'Littoral Region', 'Bantu language spoken by the Douala people');

INSERT INTO users (email, password_hash, first_name, last_name, level) VALUES
('test@lingocameroon.com', '$2y$10$PZgNKOOkrqH/PXNR7.KUuO/WfQr7FhBzA8xjtcgC5KCbid/zrySeK', 'Mbolo', 'Student', 'Intermediate');


INSERT INTO vocabulary (language_id, word, translation, phonetic, example_sentence, category, difficulty_level) VALUES
(1, 'Mbolo', 'Hello / Greetings', 'MBO-lo', 'Mbolo! Ye o ye mvae?', 'Greetings', 'Beginner'),
(1, 'Ambe', 'Who', 'AM-be', 'Ambe a ye?', 'Pronouns', 'Beginner'),
(1, 'Akiba', 'Thank you', 'a-KI-ba', 'Akiba mbamba!', 'Greetings', 'Beginner'),
(1, 'Mvae', 'Good / Well', 'M-VAE', 'Me ye mvae', 'Adjectives', 'Beginner'),
(1, 'Nlem', 'Heart / Mind', 'N-LEM', 'Nlem nga be', 'Body', 'Beginner'),
(1, 'Bana', 'Children', 'BA-na', 'Bana ba ye mvae', 'Family', 'Beginner'),
(2, 'Mbombolo', 'Hello', 'MBOM-bo-lo', 'Mbombolo, nde?', 'Greetings', 'Beginner'),
(2, 'Ane', 'Mother', 'A-NE', 'Ane a ye mvae', 'Family', 'Beginner'),
(2, 'Ese', 'House', 'E-SE', 'Ese nga be', 'Places', 'Beginner');

INSERT INTO cultural_content (language_id, title, content, content_type, category, region, author) VALUES
(1, 'The Legend of the Bamoun King', 'The Bamoun Kingdom, located in the Western Grassfields of Cameroon, has a rich history dating back to the 14th century. The Fon (king) of Bamoun is not only a political leader but also a spiritual figure who embodies the wisdom and traditions of the people. The famous King Njoya invented the Bamoun script, one of the few indigenous writing systems in Africa.', 'article', 'History', 'West Region', 'Mboa Team'),
(3, 'Traditions of the West', 'The Bamileke people are renowned for their intricate social structures and the breathtaking Fon''s palaces. The Ndop cloth, with its symbolic geometric patterns, tells stories of lineage, status, and spiritual beliefs. The Lali dance, performed during important ceremonies, connects the living with their ancestors through rhythm and movement.', 'article', 'Culture', 'Western Grassfields', 'Mboa Team'),
(5, 'Littoral Stories: The Ngondo Festival', 'The Ngondo festival is an annual celebration of the Sawa people along the Wouri River in Douala. It honors the spirits of the water and celebrates the maritime heritage of the coastal communities. The festival features traditional music, dance, and the famous Makossa rhythm that has influenced African music worldwide.', 'article', 'Festival', 'Littoral Region', 'Mboa Team'),
(4, 'Northern History: The Mandara Kingdom', 'From the Mandara Mountains to the Sahel plains, the northern regions of Cameroon hold ancient civilizations that shaped the cultural landscape. The Musgum people built remarkable domed clay structures that remain architectural marvels today.', 'article', 'History', 'Far North', 'Mboa Team'),
(2, 'Beti Oral Traditions', 'The Beti people of the Center Region have a rich tradition of oral storytelling. Tales of the tortoise, the leopard, and the elephant teach moral lessons and preserve the wisdom of generations. These stories are traditionally told around the fire in the evening.', 'article', 'Storytelling', 'Center Region', 'Mboa Team');

-- ============================================
-- LIVEKIT - ROOMS
-- ============================================
CREATE TABLE livekit_rooms (
    id SERIAL PRIMARY KEY,
    room_name VARCHAR(255) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    host_user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    language_id INT REFERENCES languages(id) ON DELETE SET NULL,
    max_participants INT DEFAULT 10,
    status VARCHAR(50) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- LIVEKIT - ROOM PARTICIPANTS
CREATE TABLE livekit_room_participants (
    id SERIAL PRIMARY KEY,
    room_id INT NOT NULL REFERENCES livekit_rooms(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP,
    UNIQUE (room_id, user_id)
);

-- WEBRTC - SIGNALS
CREATE TABLE IF NOT EXISTS webrtc_signals (
    id            SERIAL PRIMARY KEY,
    session_id    INT NOT NULL REFERENCES live_practice_sessions(id) ON DELETE CASCADE,
    from_user_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    to_user_id    INT REFERENCES users(id) ON DELETE CASCADE,
    signal_type   VARCHAR(20) NOT NULL,
    payload       TEXT NOT NULL,
    consumed      BOOLEAN DEFAULT FALSE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_webrtc_signals_session
    ON webrtc_signals(session_id, consumed, created_at);

CREATE INDEX IF NOT EXISTS idx_webrtc_signals_to_user
    ON webrtc_signals(to_user_id, consumed);
