<template>
  <LayoutSideBar>
    <div
      class="relative w-full bg-primary text-white rounded-xl text-xl shadow-btnUni
        overflow-hidden h-12 min-h-[3rem] bg-gradient-to-br from-white to-white
        transition-all ease-in-out delay-150 mb-5">
      <!-- add button -->
      <button
        class="w-full text-black h-12 text-xl cursor-pointer inline-flex justify-center items-center font-bold"
        @click.prevent="isModalCreatLetter = true">
        یادآوری جدید
      </button>
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
  <!--  modal create litter-->
  <ModalReminder
    :is-open="isModalCreatLetter"
    @close="isModalCreatLetter = false" />
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/inertia-vue3'
import LayoutSideBar from '../../Layouts/~AppLayoutSideBar.vue'

import ModalReminder from './ModalReminder.vue'

// eslint-disable-next-line no-undef
defineOptions({
  name: 'SideBarReminder'
})

const navTitle = ref([
  {
    title: 'یادآوری‌ها',
    route: 'web.user.notification.index',
    slug: '/notification'
  }
])

const isModalCreatLetter = ref(false)

// functions

</script>
