import { createRouter, createMemoryHistory, createWebHistory } from 'vue-router';
import routes from './routes';
import { apiClient } from '@/services/api/client';

const createHistory = import.meta.env.SSR ? createMemoryHistory : createWebHistory;

const router = createRouter({
  scrollBehavior: () => ({ left: 0, top: 0 }),
  routes,
  history: createHistory(import.meta.env.BASE_URL),
});

// Navigation guard for auth
router.beforeEach((to, _from, next) => {
  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);
  const isAuthenticated = apiClient.isAuthenticated();

  if (requiresAuth && !isAuthenticated) {
    next('/login');
  } else if ((to.path === '/login' || to.path === '/register') && isAuthenticated) {
    next('/');
  } else {
    next();
  }
});

export default router;
