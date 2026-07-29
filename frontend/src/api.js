import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Accept': 'application/json'
  }
});

// Interceptor para agregar token si existe (si se implementa auth con token real más adelante)
// Por ahora el login devuelve un objeto simple, pero es buena práctica tener esto.
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  // Nota: En la implementación actual enviamos el token Sanctum
  // para validar sesión en las vistas privadas protegidas por auth:sanctum
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  // Si es FormData, eliminar Content-Type para que el navegador establezca multipart/form-data correctamente
  if (config.data instanceof FormData) {
    if (config.headers) {
      delete config.headers['Content-Type'];
    }
  }
  return config;
});

export default api;
