<template>
  <ul class="text-white mb-3">
    <li
      v-for="(item , i) in folders"
      :key="i"
      class="mb-5">
      <div class="flex items-center gap-3.5 my-4 ">
        <div
          class="flex"
          @click="openChild(item)">
          <svg
            v-if="!item?.isOpen"
            class="fill-white cursor-pointer"
            width="8"
            height="9"
            viewBox="0 0 8 9"
            xmlns="http://www.w3.org/2000/svg">
            <path d="M0.257812 4.5L7.86009 0.602886L7.86009 8.39711L0.257812 4.5Z" />
          </svg>
          <svg
            v-else
            class="cursor-pointer fill-secondPrimary"
            width="9"
            height="7"
            viewBox="0 0 9 7"
            xmlns="http://www.w3.org/2000/svg">
            <path d="M4.5 7L0.602886 0.250001L8.39711 0.25L4.5 7Z" />
          </svg>
        </div>
        <Link
          :href="$route('web.user.dashboard.folder.show' , { folderId: item?.slug } )"
          class="text-xl cursor-pointer "
          :class="{ 'text-secondPrimary': item?.isOpen }">
          {{ item.name }}
        </Link>
      </div>
      <Folder
        class="mr-6"
        :class="{ 'hidden': !item?.isOpen }"
        :folders="item?.subFolders" />
    </li>
  </ul>
</template>

<script setup>
import { Link } from '@inertiajs/inertia-vue3'

defineOptions({
  name: 'Folder'
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  folders: { type: Array, required: true }
})

function openChild (item) {
  item.isOpen = !item.isOpen
}
</script>
