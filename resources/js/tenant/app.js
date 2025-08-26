// src/app.js o src/main.js

import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import "./bootstrap";
import vuetify from "@/plugins/vuetify.js";

createApp(App)
    .use(router)
    .use(vuetify)
    .mount("#app");
