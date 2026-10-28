import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin } from '@tanstack/vue-query'
import App from './App.vue'
import router from './router'
import './style.css' // atau path tailwind kamu

const app = createApp(App)

app.use(createPinia())  // harus sebelum router, karena router guard pake useAuthStore()
app.use(router)
app.use(VueQueryPlugin)

app.mount('#app')