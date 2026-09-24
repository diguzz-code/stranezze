import {
    createObservation,
    deleteObservation,
    exportObservations,
    getSession,
    getStats,
    listObservations,
    login,
    logout,
} from './api.js';
import {
    getCurrentPage,
    getFilters,
    setCsrfToken,
    setCurrentPage,
    setFilters,
} from './state.js';
import {
    elements,
    render,
    renderLoading,
    renderPagination,
    renderStats,
    showMessage,
} from './ui.js';

const today = new Date().toISOString().slice(0, 10);
elements.dateInput.value = today;
elements.dateInput.max = today;

async function loadStats() {
    const data = await getStats();
    renderStats(data);
}

async function loadItems(page = getCurrentPage()) {
    renderLoading();
    setCurrentPage(page);
    const data = await listObservations({ page, ...getFilters() });
    render(data.items, removeItem);
    renderPagination(data.pagination, loadItems);
    await loadStats();
}

async function removeItem(id) {
    if (!window.confirm('Eliminare questa osservazione?')) return;
    try {
        await deleteObservation(id);
        await loadItems();
    } catch (error) {
        showMessage(elements.message, error.message, true);
    }
}

elements.loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = elements.loginForm.querySelector('button');
    button.disabled = true;
    try {
        const loginData = new FormData(elements.loginForm);
        const data = await login(loginData.get('username'), loginData.get('password'));
        setCsrfToken(data.csrf_token);
        elements.loginScreen.hidden = true;
        elements.main.hidden = false;
        await loadItems(1);
    } catch (error) {
        showMessage(elements.loginMessage, error.message, true);
    } finally {
        button.disabled = false;
    }
});

elements.logoutButton.addEventListener('click', async () => {
    await logout();
    window.location.reload();
});

elements.exportButton.addEventListener('click', exportObservations);

elements.form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = elements.form.querySelector('button[type="submit"]');
    button.disabled = true;
    showMessage(elements.message, 'Salvataggio...');
    const formData = new FormData(elements.form);
    const payload = Object.fromEntries(formData.entries());
    payload.is_favorite = formData.has('is_favorite');
    try {
        await createObservation(payload);
        elements.form.reset();
        elements.dateInput.value = today;
        showMessage(elements.message, 'Osservazione salvata.');
        await loadItems(1);
    } catch (error) {
        showMessage(elements.message, error.message, true);
    } finally {
        button.disabled = false;
    }
});

let searchTimer;
function reloadFromFilter() {
    clearTimeout(searchTimer);
    setFilters({
        query: elements.search.value.trim(),
        category: elements.categoryFilter.value,
        favorite: elements.favoriteFilter.checked,
    });
    searchTimer = setTimeout(() => loadItems(1).catch((error) => showMessage(elements.message, error.message, true)), 250);
}

elements.search.addEventListener('input', reloadFromFilter);
elements.categoryFilter.addEventListener('change', reloadFromFilter);
elements.favoriteFilter.addEventListener('change', reloadFromFilter);

async function boot() {
    elements.main.hidden = true;
    try {
        const session = await getSession();
        if (!session.authenticated) {
            elements.loginScreen.hidden = false;
            return;
        }
        setCsrfToken(session.csrf_token);
        elements.main.hidden = false;
        await loadItems(1);
    } catch (error) {
        elements.loginScreen.hidden = false;
        showMessage(elements.loginMessage, error.message, true);
    }
}

boot();