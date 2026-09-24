import { getCsrfToken } from './state.js';

async function request(url, options = {}) {
    const headers = { ...(options.headers || {}) };
    if (getCsrfToken() && options.method && options.method !== 'GET') {
        headers['X-CSRF-Token'] = getCsrfToken();
    }
    const response = await fetch(url, { ...options, headers });
    const contentType = response.headers.get('content-type') || '';
    const data = contentType.includes('json') ? await response.json() : null;
    if (!response.ok) throw new Error(data?.error || 'Si è verificato un errore.');
    return data;
}

export function getSession() {
    return request('/api?action=session');
}

export function login(username, password) {
    return request('/api?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password }),
    });
}

export function logout() {
    return request('/api?action=logout', { method: 'POST' });
}

export function listObservations({ page, query, category, favorite }) {
    const params = new URLSearchParams({ page, per_page: 10 });
    if (query) params.set('q', query);
    if (category) params.set('category', category);
    if (favorite) params.set('favorite', '1');
    return request(`/api?${params}`);
}

export function createObservation(payload) {
    return request('/api', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
}

export function updateObservation(id, payload) {
    return request(`/api?id=${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
}

export function deleteObservation(id) {
    return request(`/api?id=${id}`, { method: 'DELETE' });
}

export function getStats() {
    return request('/api?action=stats');
}

export function exportObservations() {
    window.location.href = '/api?action=export';
}