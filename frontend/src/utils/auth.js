import api from '../api';

export const normalizeRole = (value) => {
  const normalized = String(value || '').toLowerCase();
  if (['admin'].includes(normalized)) return 'admin';
  if (['caja', 'encargado', 'encargado_caja'].includes(normalized)) return 'caja';
  if (['cocina', 'kitchen'].includes(normalized)) return 'cocina';
  if (['pedido', 'mozo'].includes(normalized)) return 'pedido';
  return normalized || 'menu';
};

export const getStoredUser = () => ({
  id: localStorage.getItem('userId') || '',
  usuario: localStorage.getItem('usuario') || '',
  nombres: localStorage.getItem('nombres') || '',
  apellidos: localStorage.getItem('apellidos') || '',
  rol: normalizeRole(localStorage.getItem('rol')),
});

export const persistAuthUser = (user) => {
  if (!user) return;
  const role = normalizeRole(user.rol || user.tipo);
  localStorage.setItem('userId', user.id ?? '');
  localStorage.setItem('usuario', user.usuario || user.name || '');
  localStorage.setItem('nombres', user.nombres || '');
  localStorage.setItem('apellidos', user.apellidos || '');
  localStorage.setItem('rol', role);
  return { ...user, rol: role };
};

export const clearAuthState = () => {
  localStorage.removeItem('token');
  localStorage.removeItem('usuario');
  localStorage.removeItem('nombres');
  localStorage.removeItem('apellidos');
  localStorage.removeItem('rol');
  localStorage.removeItem('userId');
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
};

export const roleToPath = (role) => {
  if (role === 'admin') return '/admin';
  if (role === 'caja') return '/caja';
  if (role === 'cocina') return '/cocina';
  if (role === 'pedido') return '/pedidos';
  return '/menu';
};

export const logoutSession = async (router) => {
  try {
    await api.post('/logout');
  } catch (error) {
    console.error('Logout error', error);
  } finally {
    clearAuthState();
    if (router) router.replace('/login');
    else window.location.replace('/login');
  }
};
