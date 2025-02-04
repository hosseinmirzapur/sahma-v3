<template>
  <nav
    class="flex justify-start p-3 rounded-t-xl mb-5"
    aria-label="Breadcrumb">
    <ol
      class="flex justify-start ml-10
      sm:flex sm:items-center sm:space-x-1">
      <li>
        <div class="flex items-center">
          <Link
            :href="$route('web.user.dashboard.index')"
            class=" text-sm font-medium"
            :class="!headers ? 'text-primary, cursor-default' : 'text-gray-500'">
            داشبورد
          </Link>
        </div>
      </li>
      <li
        v-for="(title, i) in headers?.slice()?.reverse()"
        :key="i">
        <div class="flex items-center">
          <span class="mx-2 text-gray-500">/</span>
          <Link
            :href="(Object.keys(headers).length <= i + 1) ? null : $route('web.user.dashboard.folder.show' , { folderId: title?.slug } )"
            class="text-sm font-medium "
            :class="(Object.keys(headers).length <= i + 1) ? 'text-primary, cursor-default pointer-events-none' : 'text-gray-500'">
            {{ title.name }}
          </Link>
        </div>
      </li>
    </ol>
  </nav>
</template>

<script setup>
import { Link } from '@inertiajs/inertia-vue3'

// eslint-disable-next-line no-undef
defineOptions({
  name: 'BreadCrumb'
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  headers: { type: Object, required: true }
})

</script>
