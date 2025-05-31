<template>
  <div class="mx-auto mt-10 w-[70%]">
    <div
      :class="countSearch > 0 ? '' : 'opacity-0'"
      class="pb-3 text-center text-sm text-gray-600"
    >
      {{ countSearch }} مورد یافت شد
    </div>

    <div class="sticky top-0 z-10 bg-gray-50 py-3 px-2">
      <div
        class="m-auto bg-white shadow-xl rounded-2xl border border-gray-200 p-4 space-y-4"
      >
        <div class="text-center text-lg text-gray-800 font-semibold">
          {{ file.name }}.{{ file.extension }}
        </div>

        <div
          class="sm:flex sm:flex-row flex-col items-center sm:gap-x-4 gap-y-3"
          dir="ltr"
        >
          <div class="flex flex-1 items-center gap-x-2 w-full">
            <audio
              ref="audioRef"
              :src="externalViewerUrl"
              loop
              @loadedmetadata="
                if (!props.file.duration_in_seconds)
                  duration = $event.target.duration;
              "
              @timeupdate="currentTime = parseInt($event.target.currentTime)"
              class="hidden"
            />
            <button
              name="پخش"
              class="bg-primary rounded-full text-white p-2 hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary-light transition-colors duration-150 flex-shrink-0"
              :class="playing ? '' : 'animate-pulse'"
              @click="togglePlay"
            >
              <PauseIcon v-if="playing" class="w-5 h-5" />
              <PlayIcon v-else class="w-5 h-5" />
            </button>
            <input
              ref="seekSlider"
              v-model="currentTime"
              type="range"
              :max="duration"
              class="progress custom-progress grow mx-2"
              step="1"
              @input="dragging"
            />
          </div>
          <div
            class="flex justify-center text-primary text-sm font-medium sm:ml-2 flex-shrink-0"
          >
            <p v-text="secondsToDuration(currentTime || 0)" />
            <p class="mx-1">/</p>
            <p v-text="secondsToDuration(duration || 0)" />
          </div>
        </div>
      </div>

      <div
        v-if="file.status === 'REGENERATING_WORD'"
        class="mt-3 text-center text-sm text-blue-600 p-3 flex items-center justify-center bg-blue-100 rounded-lg shadow-sm border border-blue-200"
      >
        <ArrowPathIcon class="w-5 h-5 mr-2 animate-spin" />
        در حال بازسازی فایل ورد...
      </div>
    </div>

    <div
      v-if="editableListValue && editableListValue.length > 0"
      class="p-5 text-base border border-green-400 mt-5 rounded-xl my-5 bg-green-50"
    >
      <div
        v-for="(item, index) in editableListValue"
        :key="item.start_time"
        class="inline-flex group relative"
      >
        <div
          v-if="index !== editingIndex"
          :ref="
            (el) => {
              if (el) textRefs[index] = el;
            }
          "
          @click.prevent="selectValue(item)"
          @dblclick="enableEditing(index)"
          class="m-1 p-1.5 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-green-500 rounded-md cursor-pointer transition-colors duration-150"
        >
          <span v-html="highlightedObject[index]" />
        </div>
        <div
          v-else
          :ref="
            (el) => {
              if (el) textRefs[index] = el;
            }
          "
          contenteditable="true"
          @blur="saveEdit(index, $event)"
          class="m-1 p-1.5 bg-white outline-none ring-2 ring-green-600 rounded-md shadow-sm"
        >
          {{ item.text }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUpdated, watch, nextTick, onMounted } from "vue";
import { PlayIcon, PauseIcon, ArrowPathIcon } from "@heroicons/vue/24/solid";
import axios from "axios";

defineOptions({
  name: "SttService",
});

const props = defineProps({
  file: { type: Object, required: true },
  externalViewerUrl: { type: String, required: false, default: null },
  listValue: {
    type: Array,
    required: true,
    default: () => [],
  },
  search: { type: String, default: "" },
  isPrint: { type: Boolean, required: false },
  printRoute: { type: String, required: true },
});

const playing = ref(false);
const audioRef = ref(null);
const duration = ref(props.file.duration_in_seconds || 0); // Initialize duration from prop
const currentTime = ref(0);
const drag = ref(false);
const countSearch = ref(0);
const emits = defineEmits([
  "search-data",
  "print-file",
  "download-file",
  "update-text",
]);

const editableListValue = ref(
  Array.isArray(props.listValue)
    ? props.listValue.map((item) => ({ ...item }))
    : [],
);
const textRefs = ref([]);
const editingIndex = ref(-1);

onUpdated(() => {
  props.search ? countHighlight() : (countSearch.value = 0);
});

function enableEditing(index) {
  editingIndex.value = index;
  nextTick(() => {
    if (textRefs.value[index]) {
      textRefs.value[index].focus();
    }
  });
}

watch(() => props.isPrint, printPDF);

watch(
  () => props.listValue,
  (newValue) => {
    editableListValue.value = Array.isArray(newValue)
      ? newValue.map((item) => ({ ...item }))
      : [];
  },
  { deep: true },
);

async function printPDF() {
  const link = document.createElement("a");
  link.href = props.printRoute;
  link.target = "_blank";
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
  if (!props.search) {
    return editableListValue.value.reduce((acc, item, index) => {
      acc[index] = item.text;
      return acc;
    }, {});
  }
  const searchRegex = new RegExp(
    props.search.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"),
    "gi",
  );
  const highlighted = {};
  editableListValue.value.forEach((item, index) => {
    highlighted[index] = item.text.replace(
      searchRegex,
      (match) => `<mark class="bg-primary/20 rounded-sm">${match}</mark>`,
    );
  });
  return highlighted;
});

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
  if (audioRef.value) {
    audioRef.value.currentTime = parseInt(currentTime.value);
    if (playing.value) {
      // Resume playing only if it was playing
      audioRef.value.play();
    }
  }
}

function secondsToDuration(s) {
  if (isNaN(s) || s < 0) {
    s = 0;
  }
  let m = Math.floor(s / 60);
  s = Math.floor(s % 60);
  return `${String(m).padStart(2, "0")} : ${String(s).padStart(2, "0")}`;
}

function selectValue(item) {
  if (audioRef.value && item.start_time !== undefined) {
    audioRef.value.currentTime = Math.round(item.start_time);
    if (!playing.value) {
      // If paused, play
      togglePlay();
    } else {
      // If already playing, ensure it plays from new time
      audioRef.value.play();
    }
  }
}

function saveEdit(index, event) {
  const updatedText = event.target.innerText.trim();
  if (editableListValue.value[index].text !== updatedText) {
    editableListValue.value[index].text = updatedText;
    sendUpdateToBackend(index, updatedText);
  }
  editingIndex.value = -1;
}

function sendUpdateToBackend(index, text) {
  const fileId = props.file.slug;
  const item = editableListValue.value[index];
  const originalId = item.id || null;

  const url = route("web.user.dashboard.file.update-asr-text", {
    fileId: fileId,
  });

  axios
    .post(url, {
      index: index,
      id: originalId,
      start_time: item.start_time,
      end_time: item.end_time,
      text: text,
    })
    .then((response) => {
      console.log("ASR text updated successfully:", response.data.message);
    })
    .catch((error) => {
      console.error("Error updating ASR text:", error);
    });
}

onMounted(() => {
  if (props.externalViewerUrl) {
    console.log("External viewer URL is set:", props.externalViewerUrl);
  }
});
</script>

<style scoped>
.progress {
  -webkit-appearance: none;
  appearance: none;
  width: 100%;
  height: 8px;
  background: #e2e8f0; /* bg-gray-300 */
  border-radius: 5px;
  outline: none;
}

.progress::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 16px;
  height: 16px;
  background: #4f46e5; /* primary color (indigo-600 as an example) */
  border-radius: 50%;
  cursor: pointer;
}

.progress::-moz-range-thumb {
  width: 16px;
  height: 16px;
  background: #4f46e5; /* primary color */
  border-radius: 50%;
  cursor: pointer;
  border: none;
}
</style>
