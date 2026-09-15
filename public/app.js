const form = document.querySelector('#observation-form');
const list = document.querySelector('#observations');
const count = document.querySelector('#count');
const search = document.querySelector('#search');
const categoryFilter = document.querySelector('#category-filter');
const favoriteFilter = document.querySelector('#favorite-filter');
const stats = document.querySelector('#stats');
const pagination = document.querySelector('#pagination');
const message = document.querySelector('#form-message');
const loginScreen = document.querySelector('#login-screen');
const loginForm = document.querySelector('#login-form');
const loginMessage = document.querySelector('#login-message');
const logoutButton = document.querySelector('#logout');
const exportButton = document.querySelector('#export');
const dateInput = document.querySelector('#observed_on');
const today = new Date().toISOString().slice(0, 10);
let csrfToken = '';
let currentPage = 1;

dateInput.value = today;
dateInput.max = today;

function showMessage(element, text, error = false) {
    element.textContent = text;
    element.style.color = error ? 'var(--coral)' : 'var(--muted)';
}

function createText(tag, text, className = '') {
    const element = document.createElement(tag);
    element.textContent = text;
    if (className) element.className = className;
    return element;
}

async function request(url, options = {}) {
    const headers = { ...(options.headers || {}) };
    if (csrfToken && options.method && options.method !== 'GET') headers['X-CSRF-Token'] = csrfToken;
    const response = await fetch(url, { ...options, headers });
    const contentType = response.headers.get('content-type') || '';
    const data = contentType.includes('json') ? await response.json() : null;
    if (!response.ok) throw new Error(data?.error || 'Si è verificato un errore.');
    return data;
}

function render(items) {
    count.textContent = `${items.length} ${items.length === 1 ? 'osservazione' : 'osservazioni'} nella pagina`;
    list.replaceChildren();
    if (items.length === 0) {
        list.append(createText('p', 'Nessuna osservazione corrisponde ai filtri.', 'empty'));
        return;
    }
    items.forEach((item) => {
        const article = document.createElement('article');
        article.className = 'observation';
        const top = document.createElement('div');
        top.className = 'observation-top';
        const title = createText('h3', item.title);
        if (Number(item.is_favorite) === 1) title.classList.add('favorite');
        top.append(title);
        const remove = createText('button', '×', 'delete');
        remove.type = 'button';
        remove.title = 'Elimina osservazione';
        remove.setAttribute('aria-label', `Elimina ${item.title}`);
        remove.addEventListener('click', () => removeItem(item.id));
        top.append(remove);
        article.append(top, createText('p', item.content));
        const meta = document.createElement('div');
        meta.className = 'meta';
        meta.append(createText('span', item.category, 'tag'), createText('span', item.observed_on));
        if (item.place) meta.append(createText('span', item.place));
        article.append(meta);
        list.append(article);
    });
}

function renderPagination(pageData) {
    pagination.replaceChildren();
    if (!pageData || pageData.pages <= 1) return;
    const previous = createText('button', 'Precedente', 'button-quiet');
    previous.disabled = pageData.page <= 1;
    previous.addEventListener('click', () => loadItems(pageData.page - 1));
    const next = createText('button', 'Successiva', 'button-quiet');
    next.disabled = pageData.page >= pageData.pages;
    next.addEventListener('click', () => loadItems(pageData.page + 1));
    pagination.append(previous, createText('span', `Pagina ${pageData.page} di ${pageData.pages}`), next);
}

async function loadItems(page = currentPage) {
    list.replaceChildren(createText('p', 'Caricamento...', 'empty'));
    currentPage = page;
    const params = new URLSearchParams({ page, per_page: 10 });
    const query = search.value.trim();
    if (query) params.set('q', query);
    if (categoryFilter.value) params.set('category', categoryFilter.value);
    if (favoriteFilter.checked) params.set('favorite', '1');
    const data = await request(`/api?${params}`);
    render(data.items);
    renderPagination(data.pagination);
    await loadStats();
}

async function loadStats() {
    const data = await request('/api?action=stats');
    stats.replaceChildren(createText('span', `${data.total} totali`), createText('span', `${data.favorites} preferite`), createText('span', `${data.categories.length} categorie`));
}

async function removeItem(id) {
    if (!window.confirm('Eliminare questa osservazione?')) return;
    try {
        await request(`/api?id=${id}`, { method: 'DELETE' });
        await loadItems();
    } catch (error) {
        showMessage(message, error.message, true);
    }
}

loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = loginForm.querySelector('button');
    button.disabled = true;
    try {
        const loginData = new FormData(loginForm);
        const data = await request('/api?action=login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ username: loginData.get('username'), password: loginData.get('password') }) });
        csrfToken = data.csrf_token;
        loginScreen.hidden = true;
        document.querySelector('main').hidden = false;
        await loadItems(1);
    } catch (error) {
        showMessage(loginMessage, error.message, true);
    } finally {
        button.disabled = false;
    }
});

logoutButton.addEventListener('click', async () => {
    await request('/api?action=logout', { method: 'POST' });
    window.location.reload();
});

exportButton.addEventListener('click', () => { window.location.href = '/api?action=export'; });

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    showMessage(message, 'Salvataggio...');
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    payload.is_favorite = formData.has('is_favorite');
    try {
        await request('/api', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        form.reset();
        dateInput.value = today;
        showMessage(message, 'Osservazione salvata.');
        await loadItems(1);
    } catch (error) {
        showMessage(message, error.message, true);
    } finally {
        button.disabled = false;
    }
});

let searchTimer;
function reloadFromFilter() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadItems(1).catch((error) => showMessage(message, error.message, true)), 250);
}
search.addEventListener('input', reloadFromFilter);
categoryFilter.addEventListener('change', reloadFromFilter);
favoriteFilter.addEventListener('change', reloadFromFilter);

async function boot() {
    document.querySelector('main').hidden = true;
    try {
        const session = await request('/api?action=session');
        if (!session.authenticated) { loginScreen.hidden = false; return; }
        csrfToken = session.csrf_token;
        document.querySelector('main').hidden = false;
        await loadItems(1);
    } catch (error) {
        loginScreen.hidden = false;
        showMessage(loginMessage, error.message, true);
    }
}

boot();
