<template>
  <div class="mx-auto mt-10">
    <!-- audio player -->
    <div
      :class="countSearch > 0 ? '' : 'opacity-0'"
      class="w-[70%] m-auto pb-3"
    >
      {{ countSearch }} مورد یافت شد
    </div>
    <div
      class="w-[70%] m-auto shadow-cardUni rounded-xl sticky top-0 bg-white z-10"
    >
      <div class="text-center text-sm text-gray-600 mb-2">{{ file.name }}</div>
      <div
        class="sm:flex md:flex-row flex-col items-center gap-2 rounded-full border border-black/24 bg-white p-2"
        dir="ltr"
      >
        <div class="flex flex-1 gap-x-1">
          <audio
            ref="audioRef"
            :src="content"
            loop
            @loadedmetadata="duration = $event.target.duration"
            @timeupdate="currentTime = parseInt($event.target.currentTime)"
          />
          <button
            name="پخش"
            class="bg-primary rounded-full text-white p-1"
            :class="playing ? '' : 'animate-pulse'"
            @click="togglePlay"
          >
            <PauseIcon v-if="playing" class="w-5 h-5" />
            <PlayIcon v-else class="w-5 h-5" />
          </button>
          <!-- progress -->
          <input
            ref="seekSlider"
            v-model="currentTime"
            type="range"
            :max="duration"
            class="progress custom-progress grow"
            step="1"
            @input="dragging"
          />
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
    <div
      v-if="editableListValue || editableListValue?.length > 0"
      class="max-w-[70%] m-auto p-5 text-base border border-green-400 mt-5 rounded-xl my-5 bg-green-50"
    >
      <div
        v-for="(item, index) in editableListValue"
        :key="index"
        class="inline-flex group relative"
      >
        <div
          :ref="
            (el) => {
              if (el) textRefs[index] = el;
            }
          "
          contenteditable="true"
          @blur="saveEdit(index, $event)"
          @click.prevent="selectValue(item, index)"
          class="m-2 p-1 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-400"
        >
          <!-- eslint-disable vue/no-v-html -->
          <span v-html="highlightedObject[index]" />
        </div>
        <button
          class="absolute top-0 right-0 mt-1 mr-1 opacity-0 group-hover:opacity-100 text-gray-500 hover:text-gray-700 focus:outline-none"
          @click="startEdit(index)"
          aria-label="Edit"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L15.232 5.232z"
            />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUpdated, watch } from "vue";
import { PlayIcon, PauseIcon } from "@heroicons/vue/24/solid";

defineOptions({
  name: "SttService",
});
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  file: { type: Object, required: true },
  content: { type: String, required: true },
  listValue: { type: Array, required: true },
  search: { type: String, default: "" },
  isPrint: { type: Boolean, required: false },
  printRoute: { type: String, required: true },
});
const playing = ref(false);
const audioRef = ref(null);
const duration = ref(0);
const currentTime = ref(0);
const drag = ref(false);
const countSearch = ref(0);
const emits = defineEmits(["print-action", "download-action", "update-text"]);

const editableListValue = ref([...props.listValue]);
const textRefs = ref([]);

onUpdated(() => {
  props.search ? countHighlight() : (countSearch.value = 0);
});

watch(() => props.isPrint, printPDF);

// Watch for changes in listValue prop and update editableListValue
watch(
  () => props.listValue,
  (newValue) => {
    editableListValue.value = [...newValue];
  },
);

async function printPDF() {
  const link = document.createElement("a");
  link.href = props.printRoute;
  link.target = "_blank"; // Open the link in a new tab/window
  try {
    const newWindow = window.open(link.href, "_blank");
    if (!newWindow) return;
    newWindow.addEventListener("load", async () => {
      await new Promise((resolve) => setTimeout(resolve, 200));
      newWindow.print();
    });
    emits("print-action");
  } catch (error) {
    console.error(error.message);
  }
}

function countHighlight() {
  const specialSpan = document.querySelectorAll("mark");
  countSearch.value = specialSpan.length;
}

const highlightedObject = computed(() => {
  const searchRegex = new RegExp(props.search, "gi");
  // Use editableListValue for highlighting
  return highlightObjectText(searchRegex, editableListValue.value);
});
// Your highlightObjectText function
const highlightObjectText = (searchRegex, objectValue) => {
  const highlightedObject = {};
  for (const key in objectValue) {
    // eslint-disable-next-line no-prototype-builtins
    if (objectValue.hasOwnProperty(key)) {
      highlightedObject[key] = objectValue[key].replace(
        searchRegex,
        (match) => `<mark  class="bg-primary/20 ">${match}</mark>`,
      );
    }
  }
  return highlightedObject;
};

function togglePlay() {
  playing.value = !playing.value;
  if (playing.value) {
    audioRef.value.play();
  } else {
    audioRef.value.pause();
  }
}

function playVoice() {
  if (audioRef?.value && audioRef?.value?.paused) {
    audioRef.value.play();
    playing.value = true;
  }
}

function dragging() {
  drag.value = false;
  playing.value = true;
  audioRef.value.currentTime = parseInt(currentTime.value);
  audioRef.value.play();
}

function secondsToDuration(s) {
  let m = s / 60;
  s = s % 60;
  let h = m / 60;
  m = m % 60;
  s = Math.floor(s);
  if (s < 10) s = "0" + s;
  m = Math.floor(m);
  if (m < 10) m = "0" + m;
  h = Math.floor(h);
  if (h < 10) h = "0" + h;
  return ` ${m}  : ${s} `;
}

function selectValue(value, sec) {
  playVoice();
  if (audioRef.value) {
    audioRef.value.currentTime = Math.round(sec);
    audioRef.value.play();
  }
}

function startEdit(index) {
  if (textRefs.value[index]) {
    textRefs.value[index].focus();
  }
}

function saveEdit(index, event) {
  const updatedText = event.target.innerText;
  // Update the local editableListValue
  editableListValue.value[index] = updatedText;
  // Call function to send update to backend
  sendUpdateToBackend(index, updatedText);
}

function sendUpdateToBackend(index, text) {
  // TODO: Implement backend communication here
  // This function should send the updated text for the specific chunk (at 'index')
  // to your backend API to update the source data and potentially trigger word file regeneration.
  console.log(`Sending update to backend: Index ${index}, Text: ${text}`);
  // Example: axios.post('/api/update-asr-text', { index: index, text: text, fileId: props.file.id });
}
</script>

<style scoped></style>
