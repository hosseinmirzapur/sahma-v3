import { createSSRApp } from 'vue'
import { createInertiaApp } from '@inertiajs/inertia-vue3'
import { InertiaProgress } from '@inertiajs/progress'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import './app.css'

// Scripts for .min-h-app
function updateHeight () {
  document.documentElement.style.setProperty('--app-height', `${window.innerHeight}px`)
}
window.addEventListener('resize', updateHeight)
updateHeight()

// Scripts for DarkMode
function updateDarkMode () {
  const state = localStorage.getItem('theme') ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
  if (state === 'dark') document.body.classList.add('dark')
  else document.body.classList.remove('dark')
}
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateDarkMode)
updateDarkMode()

createInertiaApp({
  resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
  setup ({ el, App, props, plugin }) {
    const app = createSSRApp(App, props)
    app.config.globalProperties.$route = window.route
    app.config.globalProperties.$updateDarkMode = updateDarkMode
    app.use(plugin).mount(el)
  }
})

InertiaProgress.init({
  color: '#22418c',
  delay: 0
})
