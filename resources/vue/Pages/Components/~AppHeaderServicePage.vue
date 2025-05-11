\
<template>
  <header class="fixed m-auto top-5 right-0 left-0 bottom-auto w-full bg-white">
    <div
      class="w-full flex justify-between items-center md:h-20 h-16 p-5 rounded-xl shadow-cardUni bg-primary"
    >
      <!-- search -->
      <div v-if="file?.status === 'TRANSCRIBED'" class="relative w-full">
        <input
          id="main-search"
          v-model="searchText"
          type="search"
          name="search"
          placeholder="جستجو کنید"
          class="placeholder-gray-400 bg-transparent border-x-0 border-t-0 border-b-1 !border-gray-300 text-gray-200 w-full py-2 px-4 focus:outline-none focus:bg-transparent focus:border-secondPrimary focus:ring-0 disabled:opacity-50"
          @keydown="(ev) => (ev.key === 'Enter' ? handleEnterPressed() : null)"
        />
        <MagnifyingGlassIcon class="absolute top-2 left-0 w-6 text-gray-400" />
      </div>
      <!-- end search-->

      <!-- status -->
      <button
        v-if="
          file?.status === 'WAITING_FOR_AUDIO_SEPARATION' ||
          file?.status === 'WAITING_FOR_TRANSCRIPTION' ||
          file?.status === 'WAITING_FOR_SPLIT'
        "
        class="w-[20%] items-center border px-4 py-3 rounded-xl border-gray-100 text-gray-100 cursor-pointer hover:bg-white hover:text-primary transition-all"
        @click.prevent="handleManualProcess(file)"
        v-text="aiServiceMode === 'auto' ? 'در حال پردازش' : 'پردازش دستی'"
      />
      <button
        v-if="file?.status === 'WAITING_FOR_RETRY'"
        class="w-[20%] text-white items-center border px-4 py-3 rounded-xl bg-orange-600"
        @click.prevent="tryAgain(file)"
        v-text="`پردازش مجدد`"
      />
      <button
        v-if="file?.status === 'REJECTED'"
        class="w-[20%] text-white items-center border px-4 py-3 rounded-xl bg-red-600 cursor-default"
        v-text="`پردازش نمی‌شود`"
      />
      <div class="w-full flex justify-end">
        <!--        list options-->
        <div
          v-if="file?.status === 'TRANSCRIBED'"
          class="flex items-center gap-x-3.5 relative"
        >
          <div v-for="(item, i) in options" :key="i" class="relative">
            <button
              type="button"
              class="bg-white rounded-md p-2 shadow-cardUni hover:bg-white/80 hover:shadow-btnUni"
              :class="[
                conditionItems(item.name),
                { hidden: item.name !== 'back' && authUser?.isUser },
              ]"
              @mouseover="actionTooltip(item.name, true)"
              @mouseout="actionTooltip(item.name, false)"
              @click.stop.prevent="choiceFunction(item)"
            >
              <component :is="item.icon" class="w-7 pointer-events-none" />
            </button>

            <!-- tooltips success -->
            <transition name="tooltip">
              <div
                v-if="textTooltip[item.name]"
                class="absolute top-20 w-full left-0 z-10 p-5 text-sm font-medium text-primary transition-opacity duration-300 bg-white rounded-md tooltip"
              >
                <span v-text="dicTooltips(item.name)" />
              </div>
            </transition>
          </div>
        </div>
        <div v-else class="flex items-center gap-x-3.5">
          <div v-for="(item, i) in options.slice(-3)" :key="i" class="relative">
            <button
              type="button"
              class="bg-white rounded-md p-2 shadow-cardUni hover:!bg-white/80 hover:shadow-btnUni"
              @mouseover="actionTooltip(item.name, true)"
              @mouseout="actionTooltip(item.name, false)"
              @click.stop.prevent="choiceFunction(item)"
            >
              <component :is="item.icon" class="w-7 pointer-events-none" />
            </button>
            <!-- tooltip process-->
            <transition name="tooltip">
              <div
                v-if="textTooltip[item.name]"
                class="absolute top-20 w-full left-0 z-10 p-5 text-sm font-medium text-primary transition-opacity duration-300 bg-white rounded-md tooltip"
              >
                <span v-text="dicTooltips(item.name)" />
              </div>
            </transition>
          </div>
        </div>

        <!-- download dropdown -->
        <ul
          v-if="isDownloadDropDown"
          class="absolute top-20 left-20 py-2 text-sm text-black bg-white rounded-xl tooltip"
        >
          <li>
            <button
              class="block px-4 py-2 hover:text-white hover:bg-secondPrimary"
              :class="{
                hidden: file?.status !== 'TRANSCRIBED',
                hidden: fileType !== 'pdf',
              }"
              @click="downloadPdf(file, fileType)"
            >
              دانلود فایل PDF
            </button>
          </li>
          <li>
            <button
              class="block px-4 py-2 hover:text-white hover:bg-secondPrimary"
              :class="{ hidden: file?.status !== 'TRANSCRIBED' }"
              @click="DownloadWord(file, fileType)"
            >
              دانلود فایل WORD
            </button>
          </li>
          <li>
            <button
              class="block px-4 py-2 hover:text-white hover:bg-secondPrimary"
              @click="DownloadOriginal(file, fileType)"
            >
              دانلود فایل اصلی
            </button>
          </li>
        </ul>
      </div>

      <Modal
        :is-open="isInfoModal"
        :is-delete="true"
        modal-size="large"
        @click.stop
      >
        <div class="grid grid-cols-2 gap-y-2">
          <div class="flex gap-x-3">
            <p v-text="`نام فایل`" />
            <p v-text="file?.name" />
          </div>
          <div class="flex gap-x-3">
            <p v-text="`تاریخ بارگذاری`" />
            <p v-text="file?.created_at" />
          </div>
          <div class="flex gap-x-3">
            <p v-text="`فرمت فایل`" />
            <p v-text="file?.extension" />
          </div>
          <div class="flex gap-x-3">
            <p v-text="`واحد ها`" />
            <p v-for="dep in file?.departments" :key="dep.id">
              <span v-text="dep?.name" />
            </p>
          </div>
        </div>
        <hr class="mt-5" />
        <ol class="relative border-r border-gray-700 mt-5 w-full">
          <li
            v-for="(active, i) in activities"
            :key="i"
            class="flex flex-col mr-5"
          >
            <div
              class="absolute w-3 h-3 bg-gray-900 rounded-full mt-1.5 -right-1.5 border border-gray-900 z-10"
            />
            <time
              class="mb-1 text-right text-sm font-normal text-gray-900"
              v-text="active?.created_at"
            />
            <p
              class="mb-4 text-base font-normal text-gray-500 text-right"
              v-text="active?.description"
            />
          </li>
        </ol>

        <div class="flex justify-center gap-x-5 mt-5">
          <button
            class="flex-shrink-0 bg-primary hover:bg-primaryDark hover:border-primaryDark text-sm text-white w-36 py-2 px-4 rounded-xl hidden"
            type="submit"
            v-text="`پرینت`"
          />
          <button
            class="flex-shrink-0 border border-primary text-primary text-sm w-36 py-2 px-4 rounded-xl hover:border-primaryDark hover:text-primaryDark"
            type="submit"
            @click="isInfoModal = false"
            v-text="`تایید`"
          />
        </div>
      </Modal>
    </div>
  </header>
</template>
<script setup>
import {
  MagnifyingGlassIcon,
  ArrowSmallLeftIcon,
  DocumentDuplicateIcon,
  ArrowDownTrayIcon,
  PrinterIcon,
} from "@heroicons/vue/24/outline";
import { Inertia } from "@inertiajs/inertia";
import { onBeforeUnmount, reactive, ref, watch } from "vue";
import Modal from "./Modal.vue";
import HistoryIcon from "./icon/HistoryIcon.vue";

// import Alert from './Alert.vue'

// eslint-disable-next-line no-undef
defineOptions({
  name: "HeaderServicePage",
});

const emits = defineEmits(["search-data", "print-file", "download-file"]);

const props = defineProps({
  authUser: { type: Object, required: true },
  parentFolder: { type: String, default: null },
  file: { type: Object, required: true },
  searchedInput: { type: String, default: "" },
  downloadRoute: {
    type: Object,
    default: () => ({ original: null, searchable: null, word: null }),
  },
  fileType: { type: String, required: true },
  activities: { type: Array, required: true },
  aiServiceMode: { type: String },
});
const options = [
  {
    name: "print",
    icon: PrinterIcon,
    route: "web.user.dashboard.file.print",
  },

  {
    name: "copy",
    icon: DocumentDuplicateIcon,
    route: "#",
  },
  {
    name: "download",
    icon: ArrowDownTrayIcon,
    route: "web.user.dashboard.file.download",
  },
  {
    name: "info",
    icon: HistoryIcon,
    route: "",
  },
  {
    name: "back",
    icon: ArrowSmallLeftIcon,
    route: "#",
  },
];
const isAlert = ref(false);
const isCopy = ref(false);
const textTooltip = reactive({
  print: false,
  copy: false,
  download: false,
  back: false,
  info: false,
});
const isDownloadDropDown = ref(false);
const isInfoModal = ref(false);
const searchText = ref(props.searchedInput ?? "");

function actionTooltip(name, isShow) {
  textTooltip[name] = isShow;
}
function closeDropdown() {
  isInfoModal.value = false;
  isDownloadDropDown.value = false;
}

// Attach the event listener
window.addEventListener("click", closeDropdown);

// Cleanup when the component is unmounted
onBeforeUnmount(() => {
  window.removeEventListener("click", closeDropdown);
});

watch(
  () => searchText.value,
  () => {
    sendSearchText();
  },
  { immediate: true },
);

function dicTooltips(name) {
  switch (name) {
    case "copy":
      return "کپی";
    case "print":
      return "چاپ";
    case "download":
      return "دانلود";
    case "info":
      return "تاریخچه";
    case "back":
      return "بازگشت";
    default:
      return name;
  }
}

function conditionItems(name) {
  switch (name) {
    case "copy":
      return isAlert.value ? "!bg-green-500 hover:!bg-green-500" : "";
    case "print":
      return "";
    case "download":
      // The main download button should be visible if any download route is available.
      // The dropdown itself will handle individual link visibility.
      return props.downloadRoute?.original ||
        props.downloadRoute?.searchable ||
        props.downloadRoute?.word
        ? ""
        : "!hidden";
    default:
      return name;
  }
}

function sendSearchText() {
  emits("search-data", searchText.value);
}

function choiceFunction(item) {
  switch (item.name) {
    case "back":
      backToFolder();
      break;
    case "info":
      InfoModal();
      break;
    case "print":
      executePrint();
      break;
    case "download":
      download();
      // downloadFile(item.route, props.file?.id)
      break;
    case "copy":
      copyContent(props.file?.transcribeResult);
      break;
    default:
      return null;
  }
}

function tryAgain(file) {
  Inertia.visit(
    route("web.user.dashboard.file.transcribe", { fileId: file?.slug }),
    {
      method: "post",
      replace: true,
      preserveState: true,
      // onError: (e) => {
      //   console.log(e)
      // }
    },
  );
}

const backToFolder = () => {
  Inertia.visit(
    props.searchedInput
      ? route("web.user.dashboard.search-form")
      : props.parentFolder,
    {
      method: "get",
      data: props.searchedInput ? { searchable_text: props.searchedInput } : "",
      replace: true,
      preserveState: true, // Add preserveState option
      // onError: (e) => {
      //   console.log(e)
      // }
    },
  );
};

const InfoModal = () => {
  isInfoModal.value = !isInfoModal.value;
};

const download = () => {
  isDownloadDropDown.value = !isDownloadDropDown.value;
};

function downloadPdf(file, type) {
  isDownloadDropDown.value = false;
  const link = document.createElement("a");
  if (!props.downloadRoute?.searchable) {
    console.warn("Searchable download route is not available.");
    return;
  }
  link.href = props.downloadRoute.searchable;
  link.target = "_blank"; // Open the link in a new tab/window
  link.download = `${file.name}.${type}`; // Specify the desired download filename

  link.click();
}

function DownloadWord(file, type) {
  isDownloadDropDown.value = false;
  const link = document.createElement("a");
  if (!props.downloadRoute?.word) {
    console.warn("Word download route is not available.");
    return;
  }
  link.href = props.downloadRoute.word;
  link.target = "_blank"; // Open the link in a new tab/window
  link.download = `${file.name}.${type}`; // Specify the desired download filename

  link.click();
}

function DownloadOriginal(file, type) {
  isDownloadDropDown.value = false;
  const link = document.createElement("a");
  if (!props.downloadRoute?.original) {
    console.warn("Original download route is not available.");
    return;
  }
  link.href = props.downloadRoute.original;
  link.target = "_blank"; // Open the link in a new tab/window
  link.download = `${file.name}.${type}`; // Specify the desired download filename

  link.click();
}

function copyContent(v) {
  isCopy.value = true;
  const textarea = document.createElement("textarea");
  textarea.value = v;
  textarea.style.position = "fixed";
  textarea.style.opacity = 0;
  document.body.appendChild(textarea);
  textarea.focus();
  textarea.select();
  document.execCommand("copy");
  document.body.removeChild(textarea);
  isCopy.value = false;
  isAlert.value = true;
  setTimeout(() => {
    isAlert.value = false;
  }, 3000);
}
const findInput = ref(null);

const handleEnterPressed = () => {
  findInput.value = document.getElementById("findInput");
  const enterEvent = new KeyboardEvent("keydown", {
    key: "Enter",
    keyCode: 13,
    code: "Enter",
    which: 13,
    bubbles: true,
    cancelable: true,
  });

  if (findInput.value) findInput.value?.dispatchEvent(enterEvent);
};

const handleManualProcess = (file) => {
  if (props.aiServiceMode === "auto") {
    return;
  }
  Inertia.visit(
    route("web.user.dashboard.file.manual-process", { fileId: file?.slug }),
    {
      method: "post",
      replace: true,
      preserveState: true,
      onError: (e) => {
        console.log(e);
      },
    },
  );
};

function executePrint() {
  emits("print-file");
}
</script>
<style scoped lang="scss">
.tooltip-enter-active {
  transition: all 0.5s ease-out;
}

.tooltip-leave-active {
  transition: all 0.1s cubic-bezier(1, 0.5, 0.8, 1);
}

.tooltip-enter-from,
.tooltip-leave-to {
  transform: translateY(10px);
  opacity: 0;
}
</style>
