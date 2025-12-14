import '@quasar/extras/material-icons/material-icons.css';
import { Dialog, Notify, Quasar } from 'quasar';
import 'quasar/src/css/index.sass';
import { createApp } from 'vue';
import App from './App.vue';
import i18n from './i18n';
import router from './router';

const app = createApp(App);

app.use(Quasar, {
    plugins: {
        Notify,
        Dialog
    }, // import Quasar plugins and add here
});

app.use(i18n);
app.use(router);

app.mount('#app');
