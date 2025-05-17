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
      <div class="text-center text-sm text-gray-600 mb-2 p-2">
        {{ file.name }}.{{ file.extension }}
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
        :key="index"
        class="inline-flex group relative"
      >
        <div
          v-if="index !== editingIndex"
          :ref="
            (el) => {
              if (el) textRefs[index] = el;
            }
          "
          @click.prevent="selectValue(item, index)"
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
          {{ item }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onUpdated, watch, nextTick } from "vue";
import { PlayIcon, PauseIcon } from "@heroicons/vue/24/solid";
import axios from "axios"; // Import axios

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

// Initialize editableListValue safely, ensuring it's always an array
const editableListValue = ref(
  Array.isArray(props.listValue) ? [...props.listValue] : [],
);
const textRefs = ref([]);
const editingIndex = ref(-1); // To track which item is being edited

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
    // Ensure newValue is an array before spreading
    editableListValue.value = Array.isArray(newValue) ? [...newValue] : [];
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
  // Reset editing index
  editingIndex.value = -1;
}

function sendUpdateToBackend(index, text) {
  const fileId = props.file.slug; // Get the file slug from props
  const url = route("web.user.dashboard.file.update-asr-text", {
    fileId: fileId,
  });

  axios
    .post(url, {
      index: index,
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
