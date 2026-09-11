import { createRouter, createWebHistory } from 'vue-router';
import { auth, loadUser } from './auth';
import LoginPage from './pages/LoginPage.vue';
import OrganizationPage from './pages/OrganizationPage.vue';
import SettingsPage from './pages/SettingsPage.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: { name: 'settings' } },
        { path: '/login', name: 'login', component: LoginPage },
        { path: '/settings', name: 'settings', component: SettingsPage, meta: { requiresAuth: true } },
        { path: '/organizations/:id', name: 'organization', component: OrganizationPage, meta: { requiresAuth: true } },
        { path: '/:pathMatch(.*)*', redirect: { name: 'settings' } },
    ],
});

router.beforeEach(async (to) => {
    if (!auth.ready) {
        await loadUser();
    }

    if (to.meta.requiresAuth && !auth.user) {
        return { name: 'login' };
    }

    if (to.name === 'login' && auth.user) {
        return { name: 'settings' };
    }

    return true;
});

export default router;
