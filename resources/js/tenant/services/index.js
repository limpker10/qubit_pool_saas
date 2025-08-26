// api/index.js
import callAPI from './connector';
import PATHS from './paths';

const API = {
    auth: {
        login(data) {
            return callAPI('post', PATHS.auth.LOGIN, {data});
        },
        logout() {
            return callAPI('post', PATHS.auth.LOGOUT);
        },
    },

    users: {
        getAuth() {
            return callAPI('get', PATHS.users.AUTH);
        },
        getProfile() {
            return callAPI('get', PATHS.users.PROFILE);
        },
        updateProfile(data) {
            return callAPI('put', PATHS.users.UPDATE, {data});
        },
    },
    products: {
        list(params) {
            return callAPI('get', PATHS.products.LIST, {data: params});
        },
        create(data) {
            return callAPI('post', PATHS.products.CREATE, {data});
        },
        update(id, data) {
            return callAPI('put', PATHS.products.UPDATE(id), {data});
        },
        delete(id) {
            return callAPI('delete', PATHS.products.DELETE(id));
        },
        search(params) {
            return callAPI('get', PATHS.products.SEARCH, {data: params});
        },
    },
    warehouses: {
        list(params) {
            return callAPI('get', PATHS.warehouses.LIST, {data: params});
        },
        create(data) {
            return callAPI('post', PATHS.warehouses.CREATE, {data});
        },
        update(id, data) {
            return callAPI('put', PATHS.warehouses.UPDATE(id), {data});
        },
        delete(id) {
            return callAPI('delete', PATHS.warehouses.DELETE(id));
        },
    },
    kardex: {
        list(params) {
            return callAPI('get', PATHS.kardex.LIST, {data: params});
        },
        create(data) {
            return callAPI('post', PATHS.kardex.CREATE, {data});
        },
        update(id, data) {
            return callAPI('put', PATHS.kardex.UPDATE(id), {data});
        },
        delete(id) {
            return callAPI('delete', PATHS.kardex.DELETE(id));
        },
    },
    categories: {
        list(params) {
            return callAPI('get', PATHS.categories.LIST, {data: params});
        },
        create(data) {
            return callAPI('post', PATHS.categories.CREATE, {data});
        },
        update(id, data) {
            return callAPI('put', PATHS.categories.UPDATE(id), {data});
        },
        delete(id) {
            return callAPI('delete', PATHS.categories.DELETE(id));
        },
    },
    units: {
        list(params) {
            return callAPI('get', PATHS.units.LIST, {data: params});
        },
        create(data) {
            return callAPI('post', PATHS.units.CREATE, {data});
        },
        update(id, data) {
            return callAPI('put', PATHS.units.UPDATE(id), {data});
        },
        delete(id) {
            return callAPI('delete', PATHS.units.DELETE(id));
        },
    },
    dashboard: {
        list(params) {
            return callAPI('get', PATHS.dashboard.LIST, {data: params});
        },
    },
    table_types: {
        list(params) {
            return callAPI('get', PATHS.table_types.LIST, {data: params});
        },
    },
    cash_sessions: {
        list(params) {
            return callAPI('get', PATHS.cash_sessions.LIST, {data: params});
        },
        show(id,params) {
            return callAPI('get', PATHS.cash_sessions.LIST_DETAILS(id), {data: params});
        },
        current(params) {
            return callAPI('get', PATHS.cash_sessions.CURRENT, {data: params});
        },
        open(data) {
            return callAPI('post', PATHS.cash_sessions.OPEN, {data});
        },
        close(id,data) {
            return callAPI('post', PATHS.cash_sessions.CLOSE(id), {data});
        },
        movements_list(params) {
            return callAPI('get', PATHS.cash_sessions.MOVEMENTS(id), {data: params});
        },
        movements_create(id,data) {
            return callAPI('post', PATHS.cash_sessions.MOVEMENTS(id), {data});
        },
    },
    pool_tables: {
        list(params) {
            return callAPI('get', PATHS.pool_tables.LIST, {data: params});
        },
        create(data) {
            return callAPI('post', PATHS.pool_tables.CREATE, {data});
        },
        update(id, data) {
            return callAPI('put', PATHS.pool_tables.UPDATE(id), {data});
        },
        delete(id) {
            return callAPI('delete', PATHS.pool_tables.DELETE(id));
        },
        start(id) {
            return callAPI('post', PATHS.pool_tables.START(id));
        },
        finish(id,data) {
            return callAPI('post', PATHS.pool_tables.FINISH(id),{data});
        },
        images(id,data) {
            const isFormData = data instanceof FormData;
            return callAPI('post', PATHS.pool_tables.IMAGE(id),{data, isMultipart: isFormData});
        },
    },
};

export default API;
