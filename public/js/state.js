let csrfToken = '';
let currentPage = 1;
let filters = {
    query: '',
    category: '',
    favorite: false,
};

export function getCsrfToken() {
    return csrfToken;
}

export function setCsrfToken(token) {
    csrfToken = token;
}

export function getCurrentPage() {
    return currentPage;
}

export function setCurrentPage(page) {
    currentPage = page;
}

export function getFilters() {
    return { ...filters };
}

export function setFilters(nextFilters) {
    filters = { ...filters, ...nextFilters };
}