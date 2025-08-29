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
    clients: {
        list(params) {
            return callAPI('get', PATHS.clients.LIST, {data: params});
        },
        create(data) {
            return callAPI('post', PATHS.clients.CREATE, {data});
        },
        update(id, data) {
            return callAPI('put', PATHS.clients.UPDATE(id), {data});
        },
        delete(id) {
            return callAPI('delete', PATHS.clients.DELETE(id));
        },
    },plans: {
        list(params) {
            return callAPI('get', PATHS.plans.LIST, {data: params});
        },
        create(data) {
            return callAPI('post', PATHS.plans.CREATE, {data});
        },
        update(id, data) {
            return callAPI('put', PATHS.plans.UPDATE(id), {data});
        },
        delete(id) {
            return callAPI('delete', PATHS.plans.DELETE(id));
        },
    },
    modules: {
        list(params) {
            return callAPI('get', PATHS.modules.LIST, {data: params});
        },
        create(data) {
            return callAPI('post', PATHS.modules.CREATE, {data});
        },
        update(id, data) {
            return callAPI('put', PATHS.modules.UPDATE(id), {data});
        },
        delete(id) {
            return callAPI('delete', PATHS.modules.DELETE(id));
        },
    },

};

export default API;
