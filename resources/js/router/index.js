import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/login',
        name: 'Login',
        component: () => import('../views/Login.vue'),
    },
    {
        path: '/',
        component: () => import('../layouts/MainLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'Dashboard',
                component: () => import('../views/Dashboard.vue'),
            },
            {
                path: 'products',
                name: 'Products',
                component: () => import('../views/Products.vue'),
            },
            {
                path: 'categories',
                name: 'Categories',
                component: () => import('../views/Categories.vue'),
            },
            {
                path: 'suppliers',
                name: 'Suppliers',
                component: () => import('../views/Suppliers.vue'),
            },
            {
                path: 'purchases',
                name: 'Purchases',
                component: () => import('../views/Purchases.vue'),
            },
            {
                path: 'cycles',
                name: 'Cycles',
                component: () => import('../views/Cycles.vue'),
            },
            {
                path: 'sales',
                name: 'Sales',
                component: () => import('../views/Sales.vue'),
            },
            {
                path: 'reports',
                name: 'Reports',
                component: () => import('../views/Reports.vue'),
            },
            {
                path: 'settings',
                name: 'Settings',
                component: () => import('../views/Settings.vue'),
            },
            {
                path: 'users',
                name: 'Users',
                component: () => import('../views/Users.vue'),
            },
            {
                path: 'taxes',
                name: 'Taxes',
                component: () => import('../views/Taxes.vue'),
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Navigation guard
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('auth_token');
    
    if (to.meta.requiresAuth && !token) {
        next('/login');
    } else if (to.path === '/login' && token) {
        next('/');
    } else {
        next();
    }
});

export default router;
