<template>
  <div class="mx-auto mt-10">
    <!-- Display filename -->
    <div v-if="file?.name" class="text-center text-sm text-gray-600 mb-2 p-2">
      {{ file.name }}.{{ file.extension }}
    </div>

    <!-- video -->
    <div
      :class="countSearch > 0 ? '' : 'opacity-0'"
      class="w-[70%] m-auto pb-3"
    >
      {{ countSearch }} مورد یافت شد
    </div>
    <div class="w-[70%] m-auto shadow-cardUni rounded-xl">
      <div
        class="relative rounded-2xl cursor-pointer sm:aspect-video aspect-auto"
      >
        <template v-if="!playing">
          <!-- bg blur-->
          <div
            class="absolute top-0 z-10 md:backdrop-blur-md backdrop-blur-sm bg-black/30 w-full h-full rounded-2xl"
            @click.once="playVideo"
          >
            <div class="absolute justify-center inset-0 flex items-center">
              <PlayIcon
                class="w-20 z-10 fill-white hover:fill-primary transition-all"
              />
            </div>
          </div>
        </template>

        <video
          ref="videoRef"
          :controls="playing"
          class="w-full mx-auto rounded-2xl sm:aspect-video aspect-auto"
        >
          <source :src="content" type="video/mp4" />
          Your browser does not support the video tag.
        </video>
      </div>
    </div>

    <!--    result clickable-->
    <ul
      v-if="listValue || listValue?.length > 0"
      class="max-w-[70%] p-5 text-base border border-green-400 m-5 rounded-xl my-5 bg-green-50"
    >
      <div v-for="(item, index) in listValue" :key="index" class="inline-flex">
        <button
          class="m-2 p-1 hover:bg-green-100"
          @click.prevent="selectValue(item, index)"
        >
          <!-- eslint-disable vue/no-v-html -->
          <span v-html="highlightedObject[index]" />
        </button>
      </div>
    </ul>
  </div>
</template>

<script setup>
import { computed, ref, onUpdated, watch } from "vue";
import { PlayIcon } from "@heroicons/vue/24/solid";

defineOptions({
  name: "VttService",
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
const videoRef = ref(null);
const countSearch = ref(0);
const emits = defineEmits(["print-action", "download-action"]);

onUpdated(() => {
  props.search ? countHighlight() : (countSearch.value = 0);
});

watch(() => props.isPrint, printPDF);

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
  const searchRegex = new RegExp(props?.search, "gi");
  return highlightObjectText(searchRegex, props.listValue);
});

// Your highlightObjectText function
const highlightObjectText = (searchRegex, objectValue) => {
  const highlightedObject = {};
  for (const key in objectValue) {
    // eslint-disable-next-line no-prototype-builtins
    if (objectValue.hasOwnProperty(key) && searchRegex) {
      highlightedObject[key] = objectValue[key].replace(
        searchRegex,
        (match) => `<mark  class="bg-primary/20 ">${match}</mark>`,
      );
    }
  }
  return highlightedObject;
};

function playVideo() {
  if (videoRef?.value && videoRef?.value?.paused) {
    videoRef.value.play();
    playing.value = true;
  }
}

function selectValue(value, sec) {
  playVideo();
  if (videoRef.value) {
    videoRef.value.currentTime = Math.round(sec);
  }
}
</script>
