// public/js/api.js
const API = {
    baseUrl: 'api.php?route=',

    async request(endpoint, options = {}) {
        // Получаем токен из localStorage или cookie
        let token = localStorage.getItem('token');
        
        // Если нет в localStorage, пробуем получить из cookie
        if (!token) {
            const cookies = document.cookie.split(';');
            for (let cookie of cookies) {
                const [name, value] = cookie.trim().split('=');
                if (name === 'auth_token') {
                    token = value;
                    break;
                }
            }
        }
        
        const url = `${this.baseUrl}${endpoint}`;
        console.log(`API Request: ${options.method || 'GET'} ${url}`, options.body || '');
        
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const config = {
            ...options,
            headers: {
                ...headers,
                ...(options.headers || {})
            },
            credentials: 'include' // Важно для передачи cookies
        };

        try {
            const response = await fetch(url, config);
            console.log(`Response status:`, response.status);
            
            let data;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Сервер вернул некорректный ответ');
            }
            
            console.log(`Response data:`, data);

            if (!response.ok) {
                const error = new Error(data.message || 'Ошибка запроса');
                error.status = response.status;
                error.data = data;
                throw error;
            }

            return data;

        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    get(endpoint) { 
        return this.request(endpoint, { method: 'GET' }); 
    },
    
    post(endpoint, data) { 
        return this.request(endpoint, { 
            method: 'POST', 
            body: JSON.stringify(data) 
        }); 
    },
    
    put(endpoint, data) { 
        return this.request(endpoint, { 
            method: 'PUT', 
            body: JSON.stringify(data) 
        }); 
    },
    
    delete(endpoint) { 
        return this.request(endpoint, { method: 'DELETE' }); 
    },

    setToken(token) {
        if (token) {
            localStorage.setItem('token', token);
            // Также устанавливаем cookie для PHP сессий
            document.cookie = `auth_token=${token}; path=/; max-age=${30*24*60*60}`;
        } else {
            localStorage.removeItem('token');
            document.cookie = 'auth_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        }
    }
};

const AuthAPI = {
    async login(email, password, rememberMe) {
        console.log('Login attempt:', email);
        
        const response = await API.post('auth/login', { 
            email, password, rememberMe 
        });
        
        if (response.data?.token) {
            API.setToken(response.data.token);
            localStorage.setItem('user', JSON.stringify(response.data.user));
            console.log('Login successful, user:', response.data.user);
        }
        
        return response;
    },

    async register(userData) {
        console.log('Register attempt:', userData.email);
        
        const response = await API.post('auth/register', userData);
        
        if (response.data?.token) {
            API.setToken(response.data.token);
            localStorage.setItem('user', JSON.stringify(response.data.user));
            console.log('Registration successful, user:', response.data.user);
        }
        
        return response;
    },

    async logout() {
        try {
            await API.post('auth/logout', {});
        } catch (error) {
            console.warn('Logout error:', error);
        } finally {
            API.setToken(null);
            localStorage.removeItem('user');
            window.location.href = 'index.php';
        }
    },

    getCurrentUser() {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    }
};

const EventsAPI = {
    async getAll(filter = 'all', sort = 'date_desc') {
        const response = await API.get(`events/index?filter=${filter}&sort=${sort}`);
        return response.data || response;
    },
    
    async getById(id) {
        const response = await API.get(`events/get?id=${id}`);
        return response.data || response;
    },
    
    async create(data) {
        const response = await API.post('events/index', data);
        return response;
    },
    
    async update(id, data) {
        const response = await API.put('events/update', { id, ...data });
        return response;
    },
    
    async delete(id) {
        const response = await API.delete(`events/delete?id=${id}`);
        return response;
    }
};

const TasksAPI = {
    async getByEvent(eventId) {
        const response = await API.get(`tasks/index?event_id=${eventId}`);
        return response.data || response;
    },
    
    async create(taskData) {
        const response = await API.post('tasks/index', taskData);
        return response;
    },
    
    async update(id, data) {
        const response = await API.put('tasks/update', { id, ...data });
        return response;
    },
    
    async delete(id) {
        const response = await API.delete(`tasks/delete?id=${id}`);
        return response;
    },
    
    async toggle(id, completed) {
        return this.update(id, { is_completed: completed ? 1 : 0 });
    }
};