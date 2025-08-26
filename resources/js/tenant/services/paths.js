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
    products: {
        LIST: 'api/products',                   // GET: listar clientes
        CREATE: 'api/products',                // POST: crear cliente
        SHOW: (id) => `api/products/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/products/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/products/${id}`,   // DELETE: eliminar cliente
        SEARCH: 'api/product/search',   // DELETE: eliminar cliente
    },
    warehouses: {
        LIST: 'api/warehouses',                   // GET: listar clientes
        CREATE: 'api/warehouses',                // POST: crear cliente
        SHOW: (id) => `api/warehouses/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/warehouses/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/warehouses/${id}`,   // DELETE: eliminar cliente
    },
    kardex: {
        LIST: 'api/kardex',                   // GET: listar clientes
        CREATE: 'api/kardex',                // POST: crear cliente
        SHOW: (id) => `api/kardex/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/kardex/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/kardex/${id}`,   // DELETE: eliminar cliente
    },
    categories: {
        LIST: 'api/categories',                   // GET: listar clientes
        CREATE: 'api/categories',                // POST: crear cliente
        SHOW: (id) => `api/categories/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/categories/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/categories/${id}`,   // DELETE: eliminar cliente
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
    table_types: {
        LIST: 'api/table_types',                   // GET: listar clientes
        CREATE: 'api/table_types',                // POST: crear cliente
        SHOW: (id) => `api/table_types/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/table_types/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/table_types/${id}`,   // DELETE: eliminar cliente
    },
    cash_sessions: {
        LIST: `api/cash_sessions/list`,
        LIST_DETAILS: (id) => `api/cash_sessions/${id}`,
        CURRENT: 'api/cash_session/current',
        OPEN: 'api/cash_sessions/open',
        CLOSE: (id) => `api/cash_sessions/${id}/close`,
        MOVEMENTS: (id) => `api/cash_sessions/${id}/movements`,
    },
    pool_tables: {
        LIST: 'api/tables',                   // GET: listar clientes
        CREATE: 'api/tables',                // POST: crear cliente
        SHOW: (id) => `api/tables/${id}`,     // GET: detalle de cliente
        UPDATE: (id) => `api/tables/${id}`,   // PUT/PATCH: actualizar cliente
        DELETE: (id) => `api/tables/${id}`,   // DELETE: eliminar cliente
        START: (id) => `api/tables/${id}/start`,   // DELETE: eliminar cliente
        FINISH: (id) => `api/tables/${id}/finish`,   // DELETE: eliminar cliente
        IMAGE:  (id) => `api/tables/${id}/cover`,   // DELETE: eliminar cliente
    },
};

export default PATHS;
