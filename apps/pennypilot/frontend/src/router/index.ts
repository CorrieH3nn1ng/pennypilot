import { createRouter, createMemoryHistory, createWebHistory } from 'vue-router';
import routes from './routes';
import { apiClient } from '@/services/api/client';
import { useUserStore } from '@/stores/user.store';

const createHistory = import.meta.env.SSR ? createMemoryHistory : createWebHistory;

const router = createRouter({
  scrollBehavior: () => ({ left: 0, top: 0 }),
  routes,
  history: createHistory(import.meta.env.BASE_URL),
});

// Navigation guard for auth and onboarding
router.beforeEach(async (to, _from, next) => {
  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);
  const isAuthenticated = apiClient.isAuthenticated();

  // Not authenticated - redirect to login for protected routes
  if (requiresAuth && !isAuthenticated) {
    next('/login');
    return;
  }

  // Already authenticated - redirect away from login/register
  if ((to.path === '/login' || to.path === '/register') && isAuthenticated) {
    next('/');
    return;
  }

  // Check onboarding status for authenticated users
  if (isAuthenticated && to.path !== '/onboarding') {
    const userStore = useUserStore();

    // Ensure user data is loaded
    if (!userStore.user) {
      await userStore.fetchUser();
    }

    // Redirect to onboarding if not completed (and not already going there)
    if (!userStore.onboardingCompleted && to.path !== '/onboarding') {
      next('/onboarding');
      return;
    }
  }

  // Prevent going back to onboarding if already completed
  if (to.path === '/onboarding' && isAuthenticated) {
    const userStore = useUserStore();
    if (!userStore.user) {
      await userStore.fetchUser();
    }
    if (userStore.onboardingCompleted) {
      next('/');
      return;
    }
  }

  next();
});

export default router;
