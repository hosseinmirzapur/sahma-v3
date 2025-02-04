<template>
  <ul class="text-black mb-3">
    <li
      v-for="(item , i) in folders"
      :key="i"
      class="mb-2">
      <div class="flex items-center gap-3.5 ">
        <div class="flex">
          <svg
            v-if="!item?.isOpen && isParent[item.id] "

            class="fill-black"
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
        <button
          class="text-sm cursor-pointer"
          :class="item.id===destination.id ? 'text-secondPrimary' : ''"
          @click.prevent="openChild(item)">
          {{ item.name }}
        </button>
      </div>
      <MoveFolder
        v-if="item.isOpen"
        class="mr-6"
        :class="{ 'hidden': !item?.isOpen }"
        :destination="destination"
        :folders="item?.subFolders"
        @data-to-parent="getItem" />
    </li>
  </ul>
</template>

<script setup>

import { ref } from 'vue'

defineOptions({
  name: 'MoveFolder'
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  folders: { type: Array, required: true },
  destination: { type: Object, required: true }
})
const emit = defineEmits(['dataToParent'])

const isParent = ref([])

function openChild (item) {
  item.isOpen = !item.isOpen
  isParent.value[item.id] = !isParent.value[item.id]
  getItem(item)
}

function getItem (item) {
  emit('dataToParent', item)
}
</script>
