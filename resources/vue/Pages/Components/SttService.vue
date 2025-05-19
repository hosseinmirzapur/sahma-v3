<template>
  <div class="mx-auto mt-10 w-[70%]">
    <!-- audio player -->
    <div
      :class="countSearch > 0 ? '' : 'opacity-0'"
      class="w-[70%] m-auto pb-3"
    >
      {{ countSearch }} مورد یافت شد
    </div>
    <div class="m-auto shadow-cardUni rounded-xl sticky top-0 bg-white z-10">
      <div class="text-center text-sm text-gray-600 mb-2 p-2"></div>
      {{ file.name }}.{{ file.extension }}
    </div>
    <!-- Display status when regenerating word -->
    <div
      v-if="file.status === 'REGENERATING_WORD'"
      class="text-center text-sm text-blue-500 mb-2 p-2 flex items-center justify-center"
    >
      <ArrowPathIcon class="w-5 h-5 mr-2 animate-spin" />
      در حال بازسازی فایل ورد...
    </div>
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
    v-if="editableListValue && editableListValue.length > 0"
    class="max-w-[70%] m-auto p-5 text-base border border-green-400 mt-5 rounded-xl my-5 bg-green-50"
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
        class="m-2 p-2 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-400"
      >
        <!-- eslint-disable vue/no-v-html -->
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
        class="m-2 p-2 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-400"
      >
        {{ item.text }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUpdated, watch, nextTick } from "vue";
import { PlayIcon, PauseIcon, ArrowPathIcon } from "@heroicons/vue/24/solid"; // Import ArrowPathIcon
import axios from "axios"; // Import axios

defineOptions({
  name: "SttService",
});

const props = defineProps({
  file: { type: Object, required: true },
  content: { type: String, required: true },
  // Updated prop type to expect an array of objects
  listValue: {
    type: Array,
    required: true,
    default: () => [], // Provide a default empty array
  },
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
const emits = defineEmits([
  "search-data",
  "print-file",
  "download-file",
  "update-text",
]); // Added update-text emit

// Initialize editableListValue safely, ensuring it's always an array of objects
const editableListValue = ref(
  Array.isArray(props.listValue)
    ? props.listValue.map((item) => ({ ...item })) // Deep copy items
    : [],
);
const textRefs = ref([]);
const editingIndex = ref(-1); // To track which item is being edited (using array index for local UI state)

onUpdated(() => {
  props.search ? countHighlight() : (countSearch.value = 0);
});

function enableEditing(index) {
  editingIndex.value = index;
  // Use nextTick to ensure the element is rendered before focusing
  nextTick(() => {
    if (textRefs.value[index]) {
      textRefs.value[index].focus();
    }
  });
}

watch(() => props.isPrint, printPDF);

// Watch for changes in listValue prop and update editableListValue
watch(
  () => props.listValue,
  (newValue) => {
    // Ensure newValue is an array before mapping and spreading
    editableListValue.value = Array.isArray(newValue)
      ? newValue.map((item) => ({ ...item }))
      : [];
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
  const highlighted = {};
  // Iterate over editableListValue (array of objects)
  editableListValue.value.forEach((item, index) => {
    highlighted[index] = item.text.replace(
      searchRegex,
      (match) => `<mark class="bg-primary/20">${match}</mark>`,
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

// Updated selectValue to use item.start_time
function selectValue(item) {
  // Removed index parameter
  playVoice();
  if (audioRef.value && item.start_time !== undefined) {
    audioRef.value.currentTime = Math.round(item.start_time);
    audioRef.value.play();
  }
}

function startEdit(index) {
  if (textRefs.value[index]) {
    textRefs.value[index].focus();
  }
}

// Updated saveEdit to use array index when calling sendUpdateToBackend
function saveEdit(index, event) {
  // Reverted parameter back to index
  const updatedText = event.target.innerText;
  // Update the local editableListValue
  editableListValue.value[index].text = updatedText;
  // Call function to send update to backend, using the array index
  sendUpdateToBackend(index, updatedText);

  // Reset editing index
  editingIndex.value = -1;
}

// Updated sendUpdateToBackend to use index parameter
function sendUpdateToBackend(index, text) {
  // Changed parameter back to index
  const fileId = props.file.slug; // Get the file slug from props
  const url = route("web.user.dashboard.file.update-asr-text", {
    fileId: fileId,
  });

  axios
    .post(url, {
      index: index, // Send the array index
      text: text,
    })
    .then((response) => {
      console.log("ASR text updated successfully:", response.data.message);
      // Optionally, show a success message to the user
    })
    .catch((error) => {
      console.error("Error updating ASR text:", error);
      // Optionally, show an error message to the user
      // You might want to revert the local change if the backend update fails
    });
}
</script>

<style scoped></style>
