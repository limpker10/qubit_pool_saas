// paths.js

const PATHS = {
    auth: {
        LOGIN: 'api/login',
        LOGOUT: 'api/logout',
    },
    users: {
        AUTH: 'api/users/auth',
        PROFILE: 'api/users/profile',
        UPDATE: 'api/users/update',
    },
    mesas: {
        LIST: 'api/mesas',                   // GET: listar clientes
        CREATE: 'api/mesas',                // POST: crear cliente
        SHOW: (id) => `api/mesas/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/mesas/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/mesas/${id}`,   // DELETE: eliminar cliente
    },

    categories: {
        LIST: 'api/categories',                   // GET: listar clientes
        CREATE: 'api/categories',                // POST: crear cliente
        SHOW: (id) => `api/categories/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/categories/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/categories/${id}`,   // DELETE: eliminar cliente
    },
    clients: {
        LIST: 'api/clients',                   // GET: listar clientes
        CREATE: 'api/clients',                // POST: crear cliente
        SHOW: (id) => `api/clients/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/clients/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/clients/${id}`,   // DELETE: eliminar cliente
    },
    plans: {
        LIST: 'api/plans',                   // GET: listar clientes
        CREATE: 'api/plans',                // POST: crear cliente
        SHOW: (id) => `api/plans/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/plans/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/plans/${id}`,   // DELETE: eliminar cliente
    },
    modules: {
        LIST: 'api/modules',                   // GET: listar clientes
        CREATE: 'api/modules',                // POST: crear cliente
        SHOW: (id) => `api/modules/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/modules/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/modules/${id}`,   // DELETE: eliminar cliente
    },
    units: {
        LIST: 'api/units',                   // GET: listar clientes
        CREATE: 'api/units',                // POST: crear cliente
        SHOW: (id) => `api/units/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/units/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/units/${id}`,   // DELETE: eliminar cliente
    },
    dashboard: {
        LIST: 'api/dashboard/kpis',                   // GET: listar clientes
    },

};

export default PATHS;
