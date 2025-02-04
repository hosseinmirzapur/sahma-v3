<template>
  <div class="flex justify-center items-center gap-x-5 py-8">
    <div class="flex justify-center items-center rounded-full text-gray-500 dark:text-black bg-gray-300/30 w-8 h-8 p-2 dark:bg-white">
      <Link
        class="flex justify-center items-center w-8 h-8 rounded-full cursor-pointer"
        :href="previous?.url"
        replace>
        <ChevronRightIcon class="w-6 h-6" />
      </Link>
    </div>

    <div class="flex items-center flex-row rounded-full text-gray-500 dark:text-black px-3 h-9 bg-gray-300/30 dark:bg-white">
      <Link
        v-for="(ln,i) in pagination"
        :key="i"
        class="flex justify-center items-center w-8 h-8 cursor-pointer"
        :class="linkClass(ln)"
        :href="ln.url"
        replace>
        {{ ln.label }}
      </Link>
    </div>

    <div class="flex justify-center items-center rounded-full text-gray-500 dark:text-black bg-gray-300/30 w-8 h-8 p-2 dark:bg-white">
      <Link
        class="flex justify-center items-center w-8 h-8 rounded-full cursor-pointer"
        :href="next?.url"
        replace>
        <ChevronLeftIcon class="w-6 h-6" />
      </Link>
    </div>
  </div>
</template>
<script setup>
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/solid'
import { Link } from '@inertiajs/inertia-vue3'
import { ref } from 'vue'

defineOptions({
  name: 'Pagination'
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  pagination: { type: Object, required: true }
})
const customPagination = ref(props?.pagination)
const previous = customPagination?.value.shift()
const next = customPagination?.value.pop()

function linkClass (ln) {
  if (ln?.active) return 'bg-blue-700/80 rounded-md !shadow-link !text-white'
  if (ln?.url === null) return 'text-gray-500'
}
</script>
