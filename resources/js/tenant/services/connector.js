import axios from 'axios';

const BASE_URL = import.meta.env.VITE_API_BASE_URL || '/';

const caller = axios.create({
    baseURL: BASE_URL,
    // Si usas SOLO token, desactiva credentials y CSRF:
    withCredentials: false,
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// --- SIEMPRE adjunta el token más reciente ---
caller.interceptors.request.use((config) => {
    const t = localStorage.getItem('auth_token');
    if (t) config.headers.Authorization = `Bearer ${t}`;
    return config;
});

// Manejo global de 401
caller.interceptors.response.use(
    (res) => res,
    (error) => {
        if (error.response?.status === 401) {
            console.warn('[Auth] 401: token inválido/expirado → logout');
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

// Utilidad opcional para setear token a mano (por si quieres)
export const setToken = (token) => {
    if (token) {
        localStorage.setItem('auth_token', token);
        caller.defaults.headers.common.Authorization = `Bearer ${token}`;
    } else {
        localStorage.removeItem('auth_token');
        delete caller.defaults.headers.common.Authorization;
    }
};

const call = async (method, endpoint, data = {}, isMultipart = false) => {
    const config = {
        headers: {}
    };

    if (isMultipart) {
        config.headers['Content-Type'] = 'multipart/form-data';
    }

    switch (method.toLowerCase()) {
        case 'get':
            return caller.get(endpoint, {  params: data });
        case 'post':
            return caller.post(endpoint, data, config);
        case 'put':
            return caller.put(endpoint, data, config);
        case 'delete':
            return caller.delete(endpoint, { ...config, data });
        default:
            throw new Error(`[callAPI] Método HTTP no soportado: ${method}`);
    }
};
const callAPI = async (method, endpoint, options = {}) => {
    try {
        const { data = {}, isMultipart = false } = options;
        const response = await call(method, endpoint, data, isMultipart);
        return response.data;
    } catch (error) {
        console.error(`[callAPI] Error en ${method.toUpperCase()} ${endpoint}`, error.response?.data || error.message);
        throw error;
    }
};

export default callAPI;
