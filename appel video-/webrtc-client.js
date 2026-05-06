/**
 * WebRTCClient — Client de visioconférence P2P pour LingoCameroon
 * Remplace livekit-client.js sans aucune dépendance externe.
 *
 * Architecture:
 *  - RTCPeerConnection par paire de participants
 *  - Signalisation via PHP long-polling (webrtc-signal.php)
 *  - STUN Google gratuit pour la traversée NAT
 *  - DataChannel pour le chat texte et les contrôles
 */

class WebRTCClient {
    constructor(apiBase, authToken) {
        this.apiBase   = apiBase;          // ex: 'http://localhost:8000/backend/api'
        this.token     = authToken;
        this.sessionId = null;
        this.userId    = null;             // rempli après getMe()
        this.localStream  = null;
        this.peers        = new Map();     // peerId -> { pc, dataChannel, stream }
        this.sinceId      = 0;
        this.polling      = false;
        this.pingInterval = null;
        this.onPeerJoined       = null;    // callback(peerId, stream, peerInfo)
        this.onPeerLeft         = null;    // callback(peerId)
        this.onMessage          = null;    // callback(peerId, text)
        this.onStatusChange     = null;    // callback(status: string)
        this.onLocalStream      = null;    // callback(stream)

        this._iceServers = [
            { urls: 'stun:stun.l.google.com:19302'  },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' },
        ];
    }

    // ── API privées ──────────────────────────────────────────────────────────

    async _fetch(endpoint, opts = {}) {
        const res = await fetch(`${this.apiBase}/${endpoint}`, {
            ...opts,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`,
                ...(opts.headers || {}),
            },
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Erreur API');
        return json.data;
    }

    async _sendSignal(type, payload, toUserId = null) {
        await this._fetch('webrtc-signal.php?action=send', {
            method: 'POST',
            body: JSON.stringify({
                session_id: this.sessionId,
                to_user_id: toUserId,
                type,
                payload,
            }),
        });
    }

    // ── Gestion des peers ────────────────────────────────────────────────────

    _createPeerConnection(peerId) {
        const pc = new RTCPeerConnection({ iceServers: this._iceServers });

        // Ajouter les tracks locaux
        if (this.localStream) {
            this.localStream.getTracks().forEach(track =>
                pc.addTrack(track, this.localStream)
            );
        }

        // Réception ICE candidates
        pc.onicecandidate = ({ candidate }) => {
            if (candidate) {
                this._sendSignal('ice-candidate', { candidate: candidate.toJSON() }, peerId);
            }
        };

        pc.oniceconnectionstatechange = () => {
            this._emit('onStatusChange', `ICE [${peerId}]: ${pc.iceConnectionState}`);
            if (pc.iceConnectionState === 'failed') {
                pc.restartIce();
            }
        };

        // Réception du stream distant
        pc.ontrack = ({ streams }) => {
            if (streams[0]) {
                const peerEntry = this.peers.get(peerId) || {};
                peerEntry.stream = streams[0];
                this.peers.set(peerId, { ...peerEntry, pc });
                this._emit('onPeerJoined', peerId, streams[0], peerEntry.info || {});
            }
        };

        // DataChannel (chat + contrôles)
        pc.ondatachannel = ({ channel }) => {
            this._setupDataChannel(channel, peerId);
        };

        const entry = this.peers.get(peerId) || {};
        this.peers.set(peerId, { ...entry, pc });
        return pc;
    }

    _setupDataChannel(channel, peerId) {
        channel.onmessage = ({ data }) => {
            try {
                const msg = JSON.parse(data);
                if (msg.type === 'chat') {
                    this._emit('onMessage', peerId, msg.text);
                }
            } catch {}
        };
        const entry = this.peers.get(peerId) || {};
        this.peers.set(peerId, { ...entry, dataChannel: channel });
    }

    async _initiateCall(peerId) {
        const pc      = this._createPeerConnection(peerId);
        const channel = pc.createDataChannel('chat', { ordered: true });
        this._setupDataChannel(channel, peerId);

        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await this._sendSignal('offer', { sdp: offer }, peerId);
        this._emit('onStatusChange', `Offer envoyée à ${peerId}`);
    }

    async _handleOffer(fromId, sdpObj) {
        let pc = this.peers.get(fromId)?.pc;
        if (!pc) pc = this._createPeerConnection(fromId);

        await pc.setRemoteDescription(new RTCSessionDescription(sdpObj));
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        await this._sendSignal('answer', { sdp: answer }, fromId);
        this._emit('onStatusChange', `Answer envoyée à ${fromId}`);
    }

    async _handleAnswer(fromId, sdpObj) {
        const pc = this.peers.get(fromId)?.pc;
        if (!pc) return;
        if (pc.signalingState === 'have-local-offer') {
            await pc.setRemoteDescription(new RTCSessionDescription(sdpObj));
        }
    }

    async _handleIceCandidate(fromId, { candidate }) {
        const pc = this.peers.get(fromId)?.pc;
        if (!pc || !candidate) return;
        try {
            await pc.addIceCandidate(new RTCIceCandidate(candidate));
        } catch {}
    }

    _handleLeave(fromId) {
        const entry = this.peers.get(fromId);
        if (entry) {
            entry.pc?.close();
            this.peers.delete(fromId);
        }
        this._emit('onPeerLeft', fromId);
    }

    // ── Long-polling ─────────────────────────────────────────────────────────

    _startPolling() {
        this.polling = true;
        this._poll();
    }

    async _poll() {
        while (this.polling) {
            try {
                const data = await this._fetch(
                    `webrtc-signal.php?action=poll&session_id=${this.sessionId}&since_id=${this.sinceId}`
                );
                if (data.signals && data.signals.length > 0) {
                    this.sinceId = data.since_id;
                    for (const sig of data.signals) {
                        await this._dispatch(sig);
                    }
                }
            } catch (err) {
                if (this.polling) {
                    // Pause 2s avant de réessayer en cas d'erreur réseau
                    await this._sleep(2000);
                }
            }
        }
    }

    async _dispatch(signal) {
        const { from_user_id: fromId, signal_type: type, payload } = signal;
        switch (type) {
            case 'offer':         return this._handleOffer(fromId, payload.sdp);
            case 'answer':        return this._handleAnswer(fromId, payload.sdp);
            case 'ice-candidate': return this._handleIceCandidate(fromId, payload);
            case 'leave':         return this._handleLeave(fromId);
        }
    }

    // ── Heartbeat ────────────────────────────────────────────────────────────

    _startHeartbeat() {
        this.pingInterval = setInterval(() => {
            this._fetch(`webrtc-signal.php?action=ping&session_id=${this.sessionId}`, {
                method: 'POST',
                body: JSON.stringify({ session_id: this.sessionId }),
            }).catch(() => {});
        }, 15_000); // toutes les 15 secondes
    }

    // ── API publique ─────────────────────────────────────────────────────────

    /**
     * Rejoindre une session de pratique live et ouvrir la caméra/micro.
     * @param {number}  sessionId    ID de la live_practice_session
     * @param {object}  [mediaOpts]  { audio: true, video: true }
     */
    async join(sessionId, mediaOpts = { audio: true, video: true }) {
        this.sessionId = sessionId;
        this._emit('onStatusChange', 'Accès aux médias…');

        try {
            this.localStream = await navigator.mediaDevices.getUserMedia(mediaOpts);
            this._emit('onLocalStream', this.localStream);
        } catch (err) {
            // Fallback audio uniquement
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                this._emit('onLocalStream', this.localStream);
                this._emit('onStatusChange', 'Caméra indisponible – audio seulement');
            } catch {
                throw new Error('Impossible d\'accéder au microphone: ' + err.message);
            }
        }

        // Récupérer les pairs déjà présents et initier les appels
        const peers = await this._fetch(`webrtc-signal.php?action=peers&session_id=${sessionId}`);
        for (const peer of peers) {
            this.peers.set(peer.id, { info: peer });
            await this._initiateCall(peer.id);
        }

        this._startPolling();
        this._startHeartbeat();
        this._emit('onStatusChange', 'Connecté');
    }

    /**
     * Activer / désactiver le microphone.
     */
    setMicrophoneEnabled(enabled) {
        this.localStream?.getAudioTracks().forEach(t => { t.enabled = enabled; });
    }

    /**
     * Activer / désactiver la caméra.
     */
    setCameraEnabled(enabled) {
        this.localStream?.getVideoTracks().forEach(t => { t.enabled = enabled; });
    }

    /**
     * Envoyer un message texte à tous les participants via DataChannel.
     */
    sendChat(text) {
        const msg = JSON.stringify({ type: 'chat', text });
        this.peers.forEach(({ dataChannel }) => {
            if (dataChannel?.readyState === 'open') {
                dataChannel.send(msg);
            }
        });
    }

    /**
     * Quitter la session proprement.
     */
    async leave() {
        this.polling = false;
        clearInterval(this.pingInterval);

        await this._sendSignal('leave', {}).catch(() => {});

        this.peers.forEach(({ pc }) => pc?.close());
        this.peers.clear();

        this.localStream?.getTracks().forEach(t => t.stop());
        this.localStream = null;

        this._emit('onStatusChange', 'Déconnecté');
    }

    /**
     * Nombre de participants (sans soi-même).
     */
    get participantCount() {
        return this.peers.size;
    }

    // ── Utilitaires ──────────────────────────────────────────────────────────

    _emit(event, ...args) {
        if (typeof this[event] === 'function') {
            try { this[event](...args); } catch {}
        }
    }

    _sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
}

// Export pour usage direct en <script> ou comme module ES
if (typeof module !== 'undefined') module.exports = WebRTCClient;
