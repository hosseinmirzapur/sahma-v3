<template>
  <div class="mx-auto mt-10 ">
    <!-- audio player -->
    <div
      :class="countSearch>0 ? '' : 'opacity-0'"
      class="w-[70%]  m-auto pb-3">
      {{ countSearch }} مورد یافت شد
    </div>
    <div class="w-[70%]  m-auto shadow-cardUni rounded-xl">
      <div
        class="sm:flex md:flex-row flex-col items-center gap-2 rounded-full border border-black/24 bg-white p-2"
        dir="ltr">
        <div class="flex flex-1 gap-x-1">
          <audio
            ref="audioRef"
            :src="content"
            loop
            @loadedmetadata="duration = $event.target.duration"
            @timeupdate="currentTime = parseInt($event.target.currentTime)" />
          <button
            name="پخش"
            class="bg-primary rounded-full text-white p-1"
            :class="playing ? '' : 'animate-pulse'"
            @click="togglePlay">
            <PauseIcon
              v-if="playing"
              class="w-5 h-5" />
            <PlayIcon
              v-else
              class="w-5 h-5" />
          </button>
          <!-- progress -->
          <input
            ref="seekSlider"
            v-model="currentTime"
            type="range"
            :max="duration"
            class="progress custom-progress grow"
            step="1"
            @input="dragging">
        </div>
        <!-- timer-->
        <div class="flex justify-center text-primary mx-2">
          <div class="flex gap-x-1 text-sm font-medium ml-3">
            <p v-text="secondsToDuration(currentTime)" />
            <p>/</p>
            <p v-text="secondsToDuration(duration)" />
          </div>
        </div>
      </div>
    </div>

    <!--    result clickable-->
    <ul
      v-if="listValue || listValue?.length>0"
      class="max-w-[70%] m-auto p-5 text-base border border-green-400 m-5 rounded-xl my-5 bg-green-50">
      <div
        v-for="(item , index) in listValue"
        :key="index"
        class="inline-flex">
        <button
          class=" m-2 p-1  hover:bg-green-100"
          @click.prevent="selectValue(item , index)">
          <!-- eslint-disable vue/no-v-html -->
          <span v-html="highlightedObject[index]" />
        </button>
      </div>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed, onUpdated, watch } from 'vue'
import { PlayIcon, PauseIcon } from '@heroicons/vue/24/solid'

defineOptions({
  name: 'SttService'
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  file: { type: Object, required: true },
  content: { type: String, required: true },
  listValue: { type: Array, required: true },
  search: { type: String, default: '' },
  isPrint: { type: Boolean, required: false },
  printRoute: { type: String, required: true }
})
const playing = ref(false)
const audioRef = ref(null)
const duration = ref(0)
const currentTime = ref(0)
const drag = ref(false)
const countSearch = ref(0)
const emits = defineEmits(['print-action', 'download-action'])

onUpdated(() => {
  props.search ? countHighlight() : countSearch.value = 0
})

watch(() => props.isPrint, printPDF)

async function printPDF () {
  const link = document.createElement('a')
  link.href = props.printRoute
  link.target = '_blank' // Open the link in a new tab/window
  try {
    const newWindow = window.open(link.href, '_blank')
    if (!newWindow) return
    newWindow.addEventListener('load', async () => {
      await new Promise(resolve => setTimeout(resolve, 200))
      newWindow.print()
    })
    emits('print-action')
  } catch (error) {
    console.error(error.message)
  }
}

function countHighlight () {
  const specialSpan = document.querySelectorAll('mark')
  countSearch.value = specialSpan.length
}

const highlightedObject = computed(() => {
  const searchRegex = new RegExp(props.search, 'gi')
  return highlightObjectText(searchRegex, props.listValue)
})
// Your highlightObjectText function
const highlightObjectText = (searchRegex, objectValue) => {
  const highlightedObject = {}
  for (const key in objectValue) {
    // eslint-disable-next-line no-prototype-builtins
    if (objectValue.hasOwnProperty(key)) {
      highlightedObject[key] = objectValue[key].replace(searchRegex, (match) => `<mark  class="bg-primary/20 ">${match}</mark>`)
    }
  }
  return highlightedObject
}

function togglePlay () {
  playing.value = !playing.value
  if (playing.value) {
    audioRef.value.play()
  } else {
    audioRef.value.pause()
  }
}

function playVoice () {
  if (audioRef?.value && audioRef?.value?.paused) {
    audioRef.value.play()
    playing.value = true
  }
}

function dragging () {
  drag.value = false
  playing.value = true
  audioRef.value.currentTime = parseInt(currentTime.value)
  audioRef.value.play()
}

function secondsToDuration (s) {
  let m = s / 60
  s = s % 60
  let h = m / 60
  m = m % 60
  s = Math.floor(s)
  if (s < 10) s = '0' + s
  m = Math.floor(m)
  if (m < 10) m = '0' + m
  h = Math.floor(h)
  if (h < 10) h = '0' + h
  return ` ${m}  : ${s} `
}

function selectValue (value, sec) {
  playVoice()
  if (audioRef.value) {
    audioRef.value.currentTime = Math.round(sec)
    audioRef.value.play()
  }
}

</script>
