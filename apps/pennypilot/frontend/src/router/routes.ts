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
      {
        path: 'income',
        name: 'income',
        component: () => import('@/pages/IncomePage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'budget',
        name: 'budget',
        component: () => import('@/pages/BudgetPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'blueprint',
        name: 'blueprint',
        component: () => import('@/pages/BlueprintPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'budget-card',
        name: 'budget-card',
        component: () => import('@/pages/BudgetCardPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'audit',
        name: 'audit',
        component: () => import('@/pages/AuditPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'fixed-expenses',
        name: 'fixed-expenses',
        component: () => import('@/pages/FixedExpensesPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'tax',
        name: 'tax',
        component: () => import('@/pages/TaxPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'intelligence',
        name: 'intelligence',
        component: () => import('@/pages/IntelligencePage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'goals',
        name: 'goals',
        component: () => import('@/pages/GoalsPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'clients',
        name: 'clients',
        component: () => import('@/pages/ClientsPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'invoices',
        name: 'invoices',
        component: () => import('@/pages/InvoicesPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'invoices/new',
        name: 'invoice-new',
        component: () => import('@/pages/InvoiceEditPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'invoices/:id',
        name: 'invoice-view',
        component: () => import('@/pages/InvoiceViewPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'invoices/:id/edit',
        name: 'invoice-edit',
        component: () => import('@/pages/InvoiceEditPage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'business-profile',
        name: 'business-profile',
        component: () => import('@/pages/BusinessProfilePage.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: 'dev/clear-slate',
        name: 'dev-clear-slate',
        component: () => import('@/pages/DevClearSlatePage.vue'),
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
        path: 'onboarding',
        name: 'onboarding',
        component: () => import('@/pages/OnboardingWizard.vue'),
        meta: { requiresAuth: true },
      },
      {
        path: ':catchAll(.*)*',
        component: () => import('@/pages/ErrorNotFound.vue'),
      },
    ],
  },
];

export default routes;
