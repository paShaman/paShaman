import { createRouter, createWebHistory } from 'vue-router';
import Onboarding from './pages/Onboarding.vue';
import Campaigns from './pages/Campaigns.vue';
import Lobby from './pages/Lobby.vue';
import Wishlist from './pages/Wishlist.vue';
import Assignment from './pages/Assignment.vue';
import Chat from './pages/Chat.vue';
import Gift from './pages/Gift.vue';
import Profile from './pages/Profile.vue';
import NotFound from './pages/NotFound.vue';

const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    { path: '/', name: 'onboarding', component: Onboarding, meta: { title: 'Тайный Санта' } },
    { path: '/campaigns', name: 'campaigns', component: Campaigns, meta: { title: 'Мои кампании' } },
    { path: '/c/:id', name: 'lobby', component: Lobby, meta: { title: 'Кампания' } },
    { path: '/c/:id/wishlist', name: 'wishlist', component: Wishlist, meta: { title: 'Вишлист' } },
    {
      path: '/c/:id/assignment',
      name: 'assignment',
      component: Assignment,
      meta: { title: 'Мой получатель' },
    },
    { path: '/c/:id/chat', name: 'chat', component: Chat, meta: { title: 'Анонимный чат' } },
    { path: '/c/:id/gift', name: 'gift', component: Gift, meta: { title: 'Подарок' } },
    { path: '/profile', name: 'profile', component: Profile, meta: { title: 'Профиль' } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFound, meta: { title: 'Не найдено' } },
  ],
  scrollBehavior(to, from, saved) {
    if (saved) return saved;
    if (to.hash) return { el: to.hash, behavior: 'smooth' };
    return { top: 0 };
  },
});

router.afterEach((to) => {
  if (typeof document !== 'undefined') {
    document.title = to.meta?.title ? `${to.meta.title} — Тайный Санта` : 'Тайный Санта';
  }
});

export default router;
