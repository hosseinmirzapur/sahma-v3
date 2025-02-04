<template>
  <LayoutSideBar>
    <ul class="mt-6 text-white">
      <li
        v-for="(item , i) in filteredNavTitle"
        :key="i"
        class="mb-5">
        <Link
          :href="$route(item.route)"
          class="text-xl cursor-pointer mx-2"
          :class="{'!text-secondPrimary' : $page.url.includes(item.slug), 'hidden' : item.slug==='/user-management' && (authUser?.isUser|| authUser?.isReadOnly)}">
          {{ item.title }}
        </Link>
      </li>
    </ul>
  </LayoutSideBar>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/inertia-vue3'
import LayoutSideBar from '../../Layouts/~AppLayoutSideBar.vue'
// eslint-disable-next-line no-undef
defineOptions({
  name: 'SideBarUM'
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, required: true }
})

const navTitle = ref([
  {
    title: 'تنظیمات پروفایل',
    route: 'web.user.profile.show',
    slug: '/profile'
  },
  {
    title: 'تنظیمات کاربران',
    route: 'web.user.user-management.index',
    slug: '/user-management'
  },
  {
    title: 'تنظیمات واحد',
    route: 'web.user.department.index',
    slug: '/department'
  }
])

const filteredNavTitle = computed(() => {
  return navTitle.value.filter(item => {
    if (item.slug === '/department') {
      return props.authUser?.isSuperAdmin
    }
    return true
  })
})
</script>

<style>
</style>
