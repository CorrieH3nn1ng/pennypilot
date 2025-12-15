import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('@/pages/DashboardPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'transactions',
        name: 'transactions',
        component: () => import('@/pages/TransactionsPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'import',
        name: 'import',
        component: () => import('@/pages/ImportPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'categories',
        name: 'categories',
        component: () => import('@/pages/CategoriesPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'settings',
        name: 'settings',
        component: () => import('@/pages/SettingsPage.vue'),
        meta: { requiresAuth: true },
      },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      {
        path: 'login',
        name: 'login',
        component: () => import('@/pages/LoginPage.vue'),
      },
      {
        path: 'register',
        name: 'register',
        component: () => import('@/pages/RegisterPage.vue'),
      },
      {
        path: ':catchAll(.*)*',
        component: () => import('@/pages/ErrorNotFound.vue'),
      },
    ],
  },
];

export default routes;
