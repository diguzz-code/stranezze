export const elements = {
    form: document.querySelector('#observation-form'),
    list: document.querySelector('#observations'),
    count: document.querySelector('#count'),
    search: document.querySelector('#search'),
    categoryFilter: document.querySelector('#category-filter'),
    favoriteFilter: document.querySelector('#favorite-filter'),
    stats: document.querySelector('#stats'),
    pagination: document.querySelector('#pagination'),
    message: document.querySelector('#form-message'),
    loginScreen: document.querySelector('#login-screen'),
    loginForm: document.querySelector('#login-form'),
    loginMessage: document.querySelector('#login-message'),
    logoutButton: document.querySelector('#logout'),
    exportButton: document.querySelector('#export'),
    dateInput: document.querySelector('#observed_on'),
    main: document.querySelector('main'),
};

export function showMessage(element, text, error = false) {
    element.textContent = text;
    element.style.color = error ? 'var(--coral)' : 'var(--muted)';
}

function createText(tag, text, className = '') {
    const element = document.createElement(tag);
    element.textContent = text;
    if (className) element.className = className;
    return element;
}

export function render(items, onRemove) {
    elements.count.textContent = `${items.length} ${items.length === 1 ? 'osservazione' : 'osservazioni'} nella pagina`;
    elements.list.replaceChildren();
    if (items.length === 0) {
        elements.list.append(createText('p', 'Nessuna osservazione corrisponde ai filtri.', 'empty'));
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
        remove.addEventListener('click', () => onRemove(item.id));
        top.append(remove);
        article.append(top, createText('p', item.content));
        const meta = document.createElement('div');
        meta.className = 'meta';
        meta.append(createText('span', item.category, 'tag'), createText('span', item.observed_on));
        if (item.place) meta.append(createText('span', item.place));
        article.append(meta);
        elements.list.append(article);
    });
}

export function renderLoading() {
    elements.list.replaceChildren(createText('p', 'Caricamento...', 'empty'));
}

export function renderPagination(pageData, onPageChange) {
    elements.pagination.replaceChildren();
    if (!pageData || pageData.pages <= 1) return;
    const previous = createText('button', 'Precedente', 'button-quiet');
    previous.disabled = pageData.page <= 1;
    previous.addEventListener('click', () => onPageChange(pageData.page - 1));
    const next = createText('button', 'Successiva', 'button-quiet');
    next.disabled = pageData.page >= pageData.pages;
    next.addEventListener('click', () => onPageChange(pageData.page + 1));
    elements.pagination.append(previous, createText('span', `Pagina ${pageData.page} di ${pageData.pages}`), next);
}

export function renderStats(data) {
    elements.stats.replaceChildren(
        createText('span', `${data.total} totali`),
        createText('span', `${data.favorites} preferite`),
        createText('span', `${data.categories.length} categorie`),
    );
}