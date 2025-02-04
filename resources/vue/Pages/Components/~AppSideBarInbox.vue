<template>
  <LayoutSideBar>
    <div
      class="relative w-full bg-primary text-white rounded-xl text-xl shadow-btnUni
        overflow-hidden h-12 min-h-[3rem] bg-gradient-to-br from-white to-white
        transition-all ease-in-out delay-150 mb-5">
      <!-- add button letter -->
      <Link
        :href="$route('web.user.cartable.submit.form')"
        class="w-full text-black h-12 text-xl cursor-pointer inline-flex justify-center items-center font-bold">
        نامه جدید
      </Link>
    </div>
    <ul class="mt-6 text-white">
      <li
        v-for="(item , i) in navTitle"
        :key="i"
        class="mb-5">
        <Link
          :href="$route(item.route)"
          class="text-xl cursor-pointer mx-2"
          :class="{'!text-secondPrimary' : $page.url.includes(item.slug)}">
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
  name: 'SideBarInbox'
})

const navTitle = ref([
  {
    title: 'نامه‌های دریافتی',
    route: 'web.user.cartable.inbox.list',
    slug: '/inbox-list'
  },
  // {
  //   title: 'کارتابل',
  //   route: 'web.user.cartable.inbox.list',
  //   slug: '/cartable'
  // },
  {
    title: 'نامه‌های ارسالی',
    route: 'web.user.cartable.submitted.list',
    slug: '/submit-list'
  },
  {
    title: 'پیش‌نویس‌ها',
    route: 'web.user.cartable.drafted.list',
    slug: '/draft-list'
  },
  {
    title: 'نامه‌های آرشیو شده',
    route: 'web.user.cartable.archived.list',
    slug: '/archived-list'
  },
  {
    title: 'نامه‌های حذف شده',
    route: 'web.user.cartable.deleted.list',
    slug: '/deleted-list'
  }
  // {
  //   title: 'پیام‌ها',
  //   route: 'web.user.report.total-transcribe-files',
  //   slug: '/inbox/'
  // }
])

// functions
</script>

<style>
</style>
