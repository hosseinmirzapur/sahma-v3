<template>
  <LayoutSideBar>
    <ul class="mt-6 text-white">
      <li
        v-for="(item , i) in navTitle"
        :key="i"
        class="mb-5">
        <Link
          :href="$route(item.route)"
          class="text-xl cursor-pointer mx-2"
          :class="{'!text-secondPrimary' : item.slug === '/report/users' ?
            $page.url.includes(item.slug) :
            $page.url===item.slug}">
          {{ item.title }}
        </Link>
      </li>
    </ul>
  </LayoutSideBar>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/inertia-vue3'
import LayoutSideBar from '../../Layouts/~AppLayoutSideBar.vue'

// eslint-disable-next-line no-undef
defineOptions({
  name: 'SideBarReport'
})

const navTitle = ref([
  {
    title: 'گزارش کاربران',
    route: 'web.user.report.users',
    slug: '/report/users'
  },
  {
    title: 'تعداد اسناد بارگذاری شده',
    route: 'web.user.report.total-files',
    slug: '/report/total-uploaded-files'
  },
  {
    title: 'فرمت اسناد بارگذاری شده',
    route: 'web.user.report.total-files-by-type',
    slug: '/report/total-uploaded-files-type'
  },
  {
    title: 'تعداد اسناد پردازش شده',
    route: 'web.user.report.total-transcribe-files',
    slug: '/report/total-uploaded-transcribe-files'
  }
])

// functions
</script>

<style>
</style>
