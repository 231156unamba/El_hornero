import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});

// Interceptor para agregar token si existe (si se implementa auth con token real más adelante)
// Por ahora el login devuelve un objeto simple, pero es buena práctica tener esto.
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token'); 
  // Nota: En la implementación actual del backend no estamos usando tokens JWT reales,
  // pero el frontend espera 'token' en localStorage para validar sesión en las vistas.
  if (token) {
    // config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
