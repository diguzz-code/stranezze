const form = document.querySelector('#observation-form');
const list = document.querySelector('#observations');
const count = document.querySelector('#count');
const search = document.querySelector('#search');
const message = document.querySelector('#form-message');
const dateInput = document.querySelector('#observed_on');
const today = new Date().toISOString().slice(0, 10);
dateInput.value = today;
dateInput.max = today;

function showMessage(text, error = false) {
    message.textContent = text;
    message.style.color = error ? 'var(--coral)' : 'var(--muted)';
}

function createText(tag, text, className = '') {
    const element = document.createElement(tag);
    element.textContent = text;
    if (className) element.className = className;
    return element;
}

function render(items) {
    count.textContent = `${items.length} ${items.length === 1 ? 'osservazione' : 'osservazioni'}`;
    list.replaceChildren();
    if (items.length === 0) {
        list.append(createText('p', 'Ancora nessuna stranezza. La prima potrebbe iniziare adesso.', 'empty'));
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
        const remove = document.createElement('button');
        remove.className = 'delete';
        remove.type = 'button';
        remove.title = 'Elimina osservazione';
        remove.setAttribute('aria-label', `Elimina ${item.title}`);
        remove.textContent = '×';
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

async function loadItems() {
    list.replaceChildren(createText('p', 'Caricamento...', 'empty'));
    const query = search.value.trim();
    const response = await fetch(`/api${query ? `?q=${encodeURIComponent(query)}` : ''}`);
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Impossibile caricare la raccolta.');
    render(data.items);
}

async function removeItem(id) {
    if (!window.confirm('Eliminare questa osservazione?')) return;
    try {
        const response = await fetch(`/api?id=${id}`, { method: 'DELETE' });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error);
        await loadItems();
    } catch (error) {
        showMessage(error.message, true);
    }
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    showMessage('Salvataggio...');
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());
    payload.is_favorite = formData.has('is_favorite');
    try {
        const response = await fetch('/api', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Impossibile salvare.');
        form.reset();
        dateInput.value = today;
        showMessage('Osservazione salvata.');
        await loadItems();
    } catch (error) {
        showMessage(error.message, true);
    } finally {
        button.disabled = false;
    }
});

let searchTimer;
search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadItems().catch((error) => showMessage(error.message, true)), 250);
});
loadItems().catch((error) => showMessage(error.message, true));
