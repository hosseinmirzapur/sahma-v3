<template>
  <div
    v-if="isOpen && isAlert"
    id="alert-border-1"
    class="fixed w-full top-[120px] left-5 lg:w-[calc(100%-460px)] rounded-t-xl
      flex justify-between items-center p-5  text-blue-800 border-t-4 border-blue-300 bg-blue-100/90"
    :class="setStatus()"
    role="alert">
    <div>
      <p
        class="text-right ml-3 text-xs md:text-sm font-medium"
        v-text="title" />
      <ul>
        <li
          v-for="(content , i) in contentsList"
          :key="i"
          class="text-right ml-3 text-xs md:text-sm font-medium"
          v-text="content" />
      </ul>
    </div>
  </div>
</template>

<script setup>
import { watch, ref } from 'vue'
// eslint-disable-next-line no-undef
defineOptions({
  name: 'Alert'
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  isOpen: { type: Boolean, required: true },
  title: { type: String, default: null },
  status: { type: String, default: 'info' }, // success || error || warning || info
  contentsList: { type: Array, default: null },
  timer: { type: Number, default: 3000 }
})
const emits = defineEmits(['close'])
const isAlert = ref(true)

function setStatus () {
  let res = ''
  switch (props.status) {
    case 'success':
      res = 'text-green-800 border-t-4 border-green-300 bg-green-100/90'
      break

    case 'error':
      res = 'text-red-800 border-t-4 border-red-300 bg-red-100/90'
      break

    case 'warning':
      res = 'text-orange-800 border-t-4 border-orange-300 bg-orange-100/90'
      break

    case 'info':
      res = 'text-blue-800 border-t-4 border-blue-300 bg-blue-100/90'
      break

    default:
      break
  }
  return res
}

watch(() => props.isOpen, () => {
  openAlert()
})

function openAlert () {
  isAlert.value = true
  setTimeout(() => {
    isAlert.value = false
    emits('close')
  }, props.timer)
}
</script>
