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
                path: 'inventory',
                name: 'Inventory',
                component: () => import('../views/Inventory.vue'),
            },
            {
                path: 'categories',
                name: 'Categories',
                component: () => import('../views/Categories.vue'),
            },
            {
                path: 'product-families',
                name: 'ProductFamilies',
                component: () => import('../views/ProductFamilies.vue'),
            },
            {
                path: 'suppliers',
                name: 'Suppliers',
                component: () => import('../views/Suppliers.vue'),
            },
            {
                path: 'distributors',
                name: 'Distributors',
                component: () => import('../views/Distributors.vue'),
            },
            {
                path: 'purchases',
                name: 'Purchases',
                component: () => import('../views/Purchases.vue'),
            },
            {
                path: 'delivery-notes',
                name: 'DeliveryNotes',
                component: () => import('../views/DeliveryNotes.vue'),
            },
            {
                path: 'return-notes',
                name: 'ReturnNotes',
                component: () => import('../views/ReturnNotes.vue'),
            },
            {
                path: 'distributor-stock',
                name: 'DistributorStock',
                component: () => import('../views/DistributorStock.vue'),
            },
            {
                path: 'sales',
                name: 'Sales',
                component: () => import('../views/Sales.vue'),
            },
            {
                path: 'clients',
                name: 'Clients',
                component: () => import('../views/Clients.vue'),
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
