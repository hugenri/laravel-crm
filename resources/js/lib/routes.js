/**
 * Todas las URLs que usa el frontend en un solo lugar. Así no se reparten
 * strings de ruta por los componentes y queda un único punto para cambiar a un
 * generador de rutas (Ziggy) cuando el mapa crezca.
 */
export const routes = {
    dashboard: () => '/dashboard',
    profile: {
        edit: () => '/profile',
        update: () => '/profile',
        password: () => '/profile/password',
    },
    users: {
        index: (query = {}) => withQuery('/users', query),
        create: () => '/users/create',
        store: () => '/users',
        show: (user) => `/users/${user}`,
        edit: (user) => `/users/${user}/edit`,
        update: (user) => `/users/${user}`,
        destroy: (user) => `/users/${user}`,
        status: (user, status) => `/users/${user}/status/${status}`,
    },
    roles: {
        index: (query = {}) => withQuery('/roles', query),
        create: () => '/roles/create',
        store: () => '/roles',
        show: (role) => `/roles/${role}`,
        edit: (role) => `/roles/${role}/edit`,
        update: (role) => `/roles/${role}`,
        destroy: (role) => `/roles/${role}`,
    },
    clients: {
        index: (query = {}) => withQuery('/clients', query),
        create: () => '/clients/create',
        store: () => '/clients',
        show: (client) => `/clients/${client}`,
        edit: (client) => `/clients/${client}/edit`,
        update: (client) => `/clients/${client}`,
        destroy: (client) => `/clients/${client}`,
        status: (client) => `/clients/${client}/status`,
        contactCreate: (client) => `/clients/${client}/contacts/create`,
        contactStore: (client) => `/clients/${client}/contacts`,
    },
    contacts: {
        index: (query = {}) => withQuery('/contacts', query),
        edit: (contact) => `/contacts/${contact}/edit`,
        update: (contact) => `/contacts/${contact}`,
        destroy: (contact) => `/contacts/${contact}`,
        primary: (contact) => `/contacts/${contact}/primary`,
    },
    opportunities: {
        index: (query = {}) => withQuery('/opportunities', query),
        create: () => '/opportunities/create',
        store: () => '/opportunities',
        show: (opportunity) => `/opportunities/${opportunity}`,
        edit: (opportunity) => `/opportunities/${opportunity}/edit`,
        update: (opportunity) => `/opportunities/${opportunity}`,
        destroy: (opportunity) => `/opportunities/${opportunity}`,
        status: (opportunity, status) => `/opportunities/${opportunity}/status/${status}`,
    },
    activities: {
        index: (query = {}) => withQuery('/activities', query),
        create: (query = {}) => withQuery('/activities/create', query),
        store: () => '/activities',
        show: (activity) => `/activities/${activity}`,
        edit: (activity) => `/activities/${activity}/edit`,
        update: (activity) => `/activities/${activity}`,
        destroy: (activity) => `/activities/${activity}`,
    },
    calendar: {
        index: (query = {}) => withQuery('/calendar', query),
    },
    reports: {
        index: (query = {}) => withQuery('/reports', query),
    },
    prospects: {
        index: (query = {}) => withQuery('/prospects', query),
        create: () => '/prospects/create',
        store: () => '/prospects',
        show: (prospect) => `/prospects/${prospect}`,
        edit: (prospect) => `/prospects/${prospect}/edit`,
        update: (prospect) => `/prospects/${prospect}`,
        destroy: (prospect) => `/prospects/${prospect}`,
        status: (prospect, status) => `/prospects/${prospect}/status/${status}`,
        convertCreate: (prospect) => `/prospects/${prospect}/convert`,
        convertStore: (prospect) => `/prospects/${prospect}/convert`,
    },
    quotes: {
        index: (query = {}) => withQuery('/quotes', query),
        create: () => '/quotes/create',
        store: () => '/quotes',
        show: (quote) => `/quotes/${quote}`,
        edit: (quote) => `/quotes/${quote}/edit`,
        update: (quote) => `/quotes/${quote}`,
        destroy: (quote) => `/quotes/${quote}`,
        status: (quote, status) => `/quotes/${quote}/status/${status}`,
        pdf: (quote) => `/quotes/${quote}/pdf`,
    },
    auth: {
        login: () => '/login',
        logout: () => '/logout',
        register: () => '/register',
        forgotPassword: () => '/forgot-password',
        resetPassword: (token) => `/reset-password/${token}`,
        verifyEmail: () => '/email/verify',
    },
};

function withQuery(path, query) {
    const params = new URLSearchParams();

    Object.entries(query).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            params.append(key, value);
        }
    });

    const queryString = params.toString();

    return queryString ? `${path}?${queryString}` : path;
}
