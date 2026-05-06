const API_BASE = 'http://localhost:8000/backend/api';

const Auth = {
    getToken() {
        return localStorage.getItem('lingo_token');
    },

    getUser() {
        const user = localStorage.getItem('lingo_user');
        return user ? JSON.parse(user) : null;
    },

    setToken(token) {
        localStorage.setItem('lingo_token', token);
    },

    setUser(user) {
        localStorage.setItem('lingo_user', JSON.stringify(user));
    },

    clear() {
        localStorage.removeItem('lingo_token');
        localStorage.removeItem('lingo_user');
    },

    isAuthenticated() {
        return !!this.getToken();
    }
};

async function apiFetch(endpoint, options = {}) {
    const token = Auth.getToken();
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };

    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const response = await fetch(`${API_BASE}/${endpoint}`, {
        ...options,
        headers
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Erreur inattendue');
    }

    return data;
}

const API = {
    async login(email, password) {
        const res = await apiFetch('auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
        Auth.setToken(res.data.token);
        Auth.setUser(res.data.user);
        return res.data;
    },

    async register(email, password, firstName, lastName) {
        const res = await apiFetch('auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify({ email, password, first_name: firstName, last_name: lastName })
        });
        Auth.setToken(res.data.token);
        Auth.setUser(res.data.user);
        return res.data;
    },

    async logout() {
        Auth.clear();
    },

    async verifyToken() {
        return await apiFetch('auth.php?action=verify');
    },

    async getUserProfile() {
        return await apiFetch('users.php');
    },

    async updateUserProfile(data) {
        return await apiFetch('users.php?action=update', {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    async updateProgress(data) {
        return await apiFetch('users.php?action=progress', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async getLanguages() {
        return await apiFetch('users.php?action=languages');
    },

    async getCourses(languageId = null) {
        const query = languageId ? `?language_id=${languageId}` : '';
        return await apiFetch(`courses.php${query}`);
    },

    async getLessons(courseId) {
        return await apiFetch(`courses.php?action=lessons&course_id=${courseId}`);
    },

    async completeLesson(lessonId, score = null) {
        const query = score ? `?score=${score}` : '';
        return await apiFetch(`courses.php?action=complete&lesson_id=${lessonId}${query}`, {
            method: 'POST',
            body: JSON.stringify({ score })
        });
    },

    async getVocabulary(languageId, category = null) {
        let query = `action=vocabulary&language_id=${languageId}`;
        if (category) query += `&category=${category}`;
        return await apiFetch(`courses.php?${query}`);
    },

    async updateVocabularyMastery(vocabularyId, masteryLevel) {
        return await apiFetch('courses.php?action=vocabulary_mastery', {
            method: 'POST',
            body: JSON.stringify({ vocabulary_id: vocabularyId, mastery_level: masteryLevel })
        });
    },

    async getChannels(languageId = null) {
        const query = languageId ? `?language_id=${languageId}` : '';
        return await apiFetch(`community.php?action=channels${query}`);
    },

    async joinChannel(channelId) {
        return await apiFetch(`community.php?action=join&channel_id=${channelId}`, {
            method: 'POST'
        });
    },

    async leaveChannel(channelId) {
        return await apiFetch(`community.php?action=leave&channel_id=${channelId}`, {
            method: 'POST'
        });
    },

    async getMessages(channelId, limit = 50, offset = 0) {
        return await apiFetch(`community.php?action=messages&channel_id=${channelId}&limit=${limit}&offset=${offset}`);
    },

    async sendMessage(channelId, content) {
        return await apiFetch('community.php?action=send_message', {
            method: 'POST',
            body: JSON.stringify({ channel_id: channelId, content })
        });
    },

    async getMembers(channelId) {
        return await apiFetch(`community.php?action=members&channel_id=${channelId}`);
    },

    async createAISession(languageId, sessionType = 'conversation') {
        return await apiFetch('ai-tutor.php?action=create_session', {
            method: 'POST',
            body: JSON.stringify({ language_id: languageId, session_type: sessionType })
        });
    },

    async getAISessions() {
        return await apiFetch('ai-tutor.php?action=sessions');
    },

    async getAIMessages(sessionId) {
        return await apiFetch(`ai-tutor.php?action=messages&session_id=${sessionId}`);
    },

    async sendAIMessage(sessionId, content) {
        return await apiFetch(`ai-tutor.php?action=send_message&session_id=${sessionId}`, {
            method: 'POST',
            body: JSON.stringify({ content })
        });
    },

    async endAISession(sessionId) {
        return await apiFetch(`ai-tutor.php?action=end_session&session_id=${sessionId}`, {
            method: 'POST'
        });
    },

    async getLiveSessions(languageId = null, status = null) {
        let url = 'live-practice.php?action=sessions';
        const params = [];
        if (languageId) params.push(`language_id=${languageId}`);
        if (status) params.push(`status=${status}`);
        if (params.length > 0) url += '&' + params.join('&');
        return await apiFetch(url);
    },

    async createLiveSession(data) {
        return await apiFetch('live-practice.php?action=create_session', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async joinLiveSession(sessionId) {
        return await apiFetch(`live-practice.php?action=join&session_id=${sessionId}`, {
            method: 'POST'
        });
    },

    async leaveLiveSession(sessionId) {
        return await apiFetch(`live-practice.php?action=leave&session_id=${sessionId}`, {
            method: 'POST'
        });
    },

    async startLiveSession(sessionId) {
        return await apiFetch(`live-practice.php?action=start&session_id=${sessionId}`, {
            method: 'POST'
        });
    },

    async endLiveSession(sessionId) {
        return await apiFetch(`live-practice.php?action=end&session_id=${sessionId}`, {
            method: 'POST'
        });
    },

    async getLiveParticipants(sessionId) {
        return await apiFetch(`live-practice.php?action=participants&session_id=${sessionId}`);
    },

    async getLibrary(languageId = null, category = null, contentType = null) {
        let query = '?';
        if (languageId) query += `language_id=${languageId}&`;
        if (category) query += `category=${category}&`;
        if (contentType) query += `content_type=${contentType}`;
        return await apiFetch(`library.php${query}`);
    },

    async getLibraryDetail(id) {
        return await apiFetch(`library.php?action=detail&id=${id}`);
    },

    async getLibraryCategories() {
        return await apiFetch('library.php?action=categories');
    },

    async getLibraryContentTypes() {
        return await apiFetch('library.php?action=content_types');
    },

    async searchUsers(query) {
        return await apiFetch(`community.php?action=search&query=${encodeURIComponent(query)}`);
    },

    async getDMConversations() {
        return await apiFetch('community.php?action=dm_conversations');
    },

    async getDMs(userId, limit = 50, offset = 0) {
        return await apiFetch(`community.php?action=dms&with_user_id=${userId}&limit=${limit}&offset=${offset}`);
    },

    async sendDM(receiverId, content) {
        return await apiFetch('community.php?action=send_dm', {
            method: 'POST',
            body: JSON.stringify({ receiver_id: receiverId, content })
        });
    }
};

function requireAuth() {
    if (!Auth.isAuthenticated()) {
        const currentPath = window.location.pathname;
        const currentPage = currentPath.split('/').pop() || 'index.html';
        window.location.href = `login.html?redirect=${currentPage}`;
        return false;
    }
    return true;
}

function updateNavUserInfo() {
    const user = Auth.getUser();
    if (!user) return;

    const nameEl = document.querySelector('aside h2.font-headline-md');
    if (nameEl) nameEl.textContent = `${user.first_name}`;

    const levelEl = document.querySelector('aside span.text-primary.font-label-md');
    if (levelEl) levelEl.textContent = `Level: ${user.level || 'Beginner'}`;

    const avatarImg = document.querySelector('aside .w-20 img, .h-10.w-10 img');
    if (avatarImg && user.avatar_url) avatarImg.src = user.avatar_url;
}

function setActiveNav(pageName) {
    document.querySelectorAll('header nav a').forEach(link => {
        link.classList.remove('text-[#A0522D]', 'font-bold', 'border-b-2', 'border-[#A0522D]', 'pb-1');
        link.classList.add('text-stone-600', 'dark:text-stone-400');
    });

    document.querySelectorAll('header nav a').forEach(link => {
        if (link.textContent.toLowerCase().includes(pageName.replace('-', ' '))) {
            link.classList.remove('text-stone-600', 'dark:text-stone-400');
            link.classList.add('text-[#A0522D]', 'font-bold', 'border-b-2', 'border-[#A0522D]', 'pb-1');
        }
    });

    document.querySelectorAll('aside nav a').forEach(link => {
        link.classList.remove('bg-white', 'dark:bg-stone-800', 'text-[#A0522D]', 'shadow-sm');
        link.classList.add('text-stone-600', 'dark:text-stone-400');
    });

    document.querySelectorAll('aside nav a').forEach(link => {
        if (link.textContent.toLowerCase().includes(pageName.replace('-', ' '))) {
            link.classList.remove('text-stone-600', 'dark:text-stone-400');
            link.classList.add('bg-white', 'dark:bg-stone-800', 'text-[#A0522D]', 'shadow-sm');
        }
    });
}

function formatTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
}

function timeAgo(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMin = Math.floor(diffMs / 60000);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffMin < 60) return `${diffMin} min ago`;
    if (diffHour < 24) return `${diffHour}h ago`;
    if (diffDay < 7) return `${diffDay}d ago`;
    return date.toLocaleDateString();
}

function getGreeting() {
    const hour = new Date().getHours();
    if (hour < 12) return 'Morning';
    if (hour < 18) return 'Afternoon';
    return 'Evening';
}

document.addEventListener('DOMContentLoaded', () => {
    if (Auth.isAuthenticated() && window.location.pathname.includes('login.html')) {
        window.location.href = 'index.html';
    }
});
