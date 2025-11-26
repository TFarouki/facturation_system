import '@quasar/extras/material-icons/material-icons.css';
import { Dialog, Notify, Quasar } from 'quasar';
import 'quasar/src/css/index.sass';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';

const app = createApp(App);

app.use(Quasar, {
    plugins: {
        Notify,
        Dialog
    }, // import Quasar plugins and add here
});

app.use(router);

app.mount('#app');
