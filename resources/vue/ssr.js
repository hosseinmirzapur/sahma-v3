import { createSSRApp } from 'vue'
import { renderToString } from '@vue/server-renderer'
import { createInertiaApp } from '@inertiajs/inertia-vue3'
import createServer from '@inertiajs/server'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'

createServer((page) => {
  if (!page.component.match(/\/(Public|Auth)\/.*/)) return null
  return createInertiaApp({
    page,
    render: renderToString,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/(Public|Auth)/**/*.vue')),
    setup ({ App, props, plugin }) {
      const app = createSSRApp(App, props)
      app.config.globalProperties.$route = () => null
      app.use(plugin)
      return app
    }
  })
})
