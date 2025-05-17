<template>
  <div class="relative mx-auto mt-10">
    <!-- Display filename -->
    <div
      v-if="content?.name"
      class="text-center text-sm text-gray-600 mb-2 p-2"
    >
      {{ content.name }}.{{ content.extension }}
    </div>

    <!-- pdf -->
    <template v-if="fileType === 'pdf'">
      <div class="w-[960px] m-auto">
        <div :class="countWord > 0 ? '' : 'opacity-0'" class="pb-3">
          {{ countWord }} مورد یافت شد
        </div>
        <!--        <iframe -->
        <!--          ref="pdfViewer"-->
        <!--          :src="file"-->
        <!--          style="height: 300px; width: 600px" />-->
        <VuePdfApp
          :config="configPDF.config"
          theme="light"
          style="height: 80vh"
          :pdf="props.file"
        />
      </div>
    </template>

    <!-- image -->
    <img
      v-else
      :src="file"
      class="w-[70%] m-auto shadow-cardUni rounded-xl"
      alt="در انتظار پردازش"
    />
  </div>
</template>

<script setup>
import { hasPersian } from "@persian-tools/persian-tools";
import VuePdfApp from "vue3-pdf-app";

import { onMounted, onUpdated, reactive, ref, watch } from "vue";

defineOptions({
  name: "IttService",
});
const props = defineProps({
  file: { type: String, required: true },
  fileType: { type: String, required: true },
  content: { type: Object, required: true },
  search: { type: String, required: true },
  isPrint: { type: Boolean, required: false },
  isDownload: { type: Boolean, required: false },
  printRoute: { type: String, required: true },
});
const findInput = ref(null);
const finder = ref(null);
const toolbar = ref(null);
const download = ref(null);
const findHighlightAll = ref(null);
const background = ref(null);
const configPDF = reactive({
  config: {
    sidebar: true,
    secondaryToolbar: false,
    toolbar: {
      toolbarViewerLeft: {
        findbar: true,
        previous: false,
        next: false,
        pageNumber: true,
        sidebarToggle: false,
      },
      toolbarViewerRight: {
        presentationMode: false,
        openFile: false,
        print: true,
        download: true,
        viewBookmark: false,
      },
      toolbarViewerMiddle: false,
    },
    errorWrapper: false,
  },
});
const emits = defineEmits(["print-action", "download-action"]);
const findResultsCount = ref(null);
const countWord = ref(null);
// const pdfViewer = ref(null)

onMounted(async () => {
  setTimeout(() => {
    getElementPDF();
    setValueInFinder();
  }, 600);
});

onUpdated(() => {
  getElementPDF();
  setValueInFinder();
});

function getElementPDF() {
  finder.value = document.getElementById("findbar");
  toolbar.value = document.getElementsByClassName("toolbar");
  findInput.value = document.getElementById("findInput");
  findHighlightAll.value = document.getElementById("findHighlightAll");
  findResultsCount.value = document.getElementById("findResultsCount");
  background.value = document.getElementById("viewerContainer");
  setStyleElementPDF();
}

function setStyleElementPDF() {
  if (finder.value) finder.value.hidden = true;
  if (toolbar.value?.length > 0) toolbar.value[0].style.zIndex = 0;
  if (background.value) background.value.style.background = "white";
  if (findHighlightAll.value) findHighlightAll.value.checked = true;
}

function getInfoCount() {
  const word = findResultsCount.value?.innerText.trim();
  countWord.value = word ? parseInt(word.split(" ")[2], 10) : null;
}

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function handleSearchChange() {
  await delay(500);
  setValueInFinder();

  await delay(200);
  getInfoCount();
}

watch(() => props.search, handleSearchChange, { immediate: true });
watch(() => props.isPrint, printPDF);

watch(
  () => props.isDownload,
  () => {
    downloadFile();
  },
);

function simulateEnterKeyPress() {
  const enterEvent = new KeyboardEvent("keydown", {
    key: "Enter",
    keyCode: 13,
    code: "Enter",
    which: 13,
    bubbles: true,
    cancelable: true,
  });

  if (findInput.value) findInput.value?.dispatchEvent(enterEvent);
}

function setValueInFinder() {
  finder.value?.classList?.remove("hidden");
  if (findInput.value) {
    findInput.value.value = reverseText(props.search);
    simulateEnterKeyPress();
  }
}

function reverseText(text) {
  if (!checkNumCharacter(text)) return;

  // check number
  if (!Number.isNaN(Number(text))) return text;
  const reverseText = text.split("").reverse().join("");
  // const words = reverseText?.split(' ').reverse().join('')
  if (hasPersian(text)) return reverseText;

  return text;
}

function checkNumCharacter(text) {
  return text.length >= 3;
}

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

function downloadFile() {
  download.value = document.getElementById("download");
  if (download.value) download.value.click();

  emits("download-action");
}
</script>

<style lang="scss">
.hiddenSmallView {
  display: none !important;
}

#toolbarContainer {
  box-shadow: none !important;
}

#toolbarViewer {
  background: white !important;
}

#viewer {
  background: white !important;
  border: none !important;

  .page {
    border: none !important;
  }
}
</style>
