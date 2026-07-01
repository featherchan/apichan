import { createApp } from 'vue'
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'
import '@/style.css'

import Console from '@/pages/remote/Console.vue'

const pathParts = window.location.pathname.split('/')
const remoteServerId = pathParts[3] || ''
const page = pathParts[4] || 'console'

let component = Console

const app = createApp(component, { remoteServerId })

app.use(Toast, {
  position: 'top-right',
  timeout: 3000,
  closeOnClick: true,
  pauseOnFocusLoss: true,
  pauseOnHover: true,
  draggable: true,
  draggablePercent: 0.6,
  showCloseButtonOnHover: false,
  hideProgressBar: false,
  closeButton: 'button',
  icon: true,
  rtl: false
})

app.mount('#app')
