import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { Quasar, Notify, Dialog, Loading, LocalStorage, SessionStorage } from 'quasar';
import router from './router';
import App from './App.vue';

import '@quasar/extras/roboto-font/roboto-font.css';
import '@quasar/extras/material-icons/material-icons.css';
import 'quasar/src/css/index.sass';
import './css/app.scss';

import { api } from './boot/axios';
import { localBaseService } from './services/storage/LocalBaseService';

const app = createApp(App);

app.use(createPinia());
app.use(router);
app.use(Quasar, {
  plugins: {
    Notify,
    Dialog,
    Loading,
    LocalStorage,
    SessionStorage,
  },
  config: {
    notify: {
      position: 'top-right',
      timeout: 3000,
    },
  },
});

app.config.globalProperties.$api = api;

localBaseService.init().then(() => {
  app.mount('#app');
});
