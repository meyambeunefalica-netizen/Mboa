-- ============================================
-- WebRTC Signalisation - Tables PostgreSQL
-- À ajouter à backend/database/schema_pgsql.sql
-- ============================================

-- Rooms de pratique live (remplace livekit_rooms)
-- La table live_practice_sessions existante est conservée
-- On ajoute seulement les tables de signalisation WebRTC

-- Signaux WebRTC (SDP offers/answers + ICE candidates)
CREATE TABLE IF NOT EXISTS webrtc_signals (
    id            SERIAL PRIMARY KEY,
    session_id    INT NOT NULL REFERENCES live_practice_sessions(id) ON DELETE CASCADE,
    from_user_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    to_user_id    INT REFERENCES users(id) ON DELETE CASCADE, -- NULL = broadcast
    signal_type   VARCHAR(20) NOT NULL,  -- 'offer', 'answer', 'ice-candidate', 'leave'
    payload       TEXT NOT NULL,         -- JSON stringifié
    consumed      BOOLEAN DEFAULT FALSE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_webrtc_signals_session
    ON webrtc_signals(session_id, consumed, created_at);

CREATE INDEX IF NOT EXISTS idx_webrtc_signals_to_user
    ON webrtc_signals(to_user_id, consumed);

-- Nettoyage automatique des vieux signaux (> 2 minutes)
-- À lancer via cron ou pg_cron: SELECT cron.schedule('*/5 * * * *', 'DELETE FROM webrtc_signals WHERE created_at < NOW() - INTERVAL ''2 minutes''');
-- Sinon le PHP les purge lui-même à chaque requête.

-- Vue pratique: participants actifs d'une session
CREATE OR REPLACE VIEW v_active_live_participants AS
SELECT
    lpp.session_id,
    lpp.user_id,
    u.first_name,
    u.last_name,
    u.avatar_url,
    lpp.joined_at
FROM live_practice_participants lpp
JOIN users u ON lpp.user_id = u.id
WHERE lpp.left_at IS NULL;
