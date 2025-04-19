<template>
  <LayoutSideBar>
    <!-- dropDown btn -->
    <div
      class="relative w-full bg-primary text-white rounded-xl text-xl shadow-btnUni overflow-hidden h-12 min-h-[3rem] bg-gradient-to-br from-white to-white transition-all ease-in-out delay-150 mb-5"
      :class="{ '!h-[9rem] !min-h-[9rem]': !isOpenAdd }"
    >
      <!-- add button -->
      <button
        class="w-full text-black h-12 text-xl cursor-pointer inline-flex justify-center items-center font-bold"
        :class="{ ' pointer-events-none opacity-50': authUser?.isUser }"
        @click.stop.prevent="isOpenAdd = !isOpenAdd"
      >
        افزودن
      </button>

      <button
        class="w-full text-black h-12 text-base hover:bg-white/10 cursor-pointer"
        @click.stop.prevent="openModals('folder')"
        v-text="`ساخت پوشه`"
      />
      <button
        class="w-full text-black h-12 text-base hover:bg-white/10 cursor-pointer"
        @click.stop.prevent="openModals('file')"
        v-text="`بارگذاری فایل`"
      />
    </div>
    <Folder :folders="folders" :is-modal="false" />
    <div
      class="flex absolute bottom-0 pb-4 bg-gradient-to-t via-primary/80 from-primary w-[85%] justify-center gap-x-5"
    >
      <div
        class="w-8 transform cursor-pointer"
        @click.stop.prevent="openArchivePage"
        @mouseover="isTooltipArchive = true"
        @mouseout="isTooltipArchive = false"
      >
        <ArchiveBoxIcon
          class="text-white cursor-pointer pointer-events-none"
          :class="{
            ' pointer-events-none opacity-50':
              authUser?.isUser || authUser?.isReadOnly,
          }"
        />
        <transition name="tooltip">
          <div
            v-if="isTooltipArchive"
            class="absolute bottom-9 right-0 z-10 px-5 py-2 text-sm font-medium text-primary transition-opacity duration-300 bg-white rounded-md tooltip"
          >
            آرشیو
          </div>
        </transition>
      </div>
      <div
        class="w-8 transform cursor-pointer"
        @click.stop.prevent="openDeletePage"
        @mouseover="isTooltipDelete = true"
        @mouseout="isTooltipDelete = false"
      >
        <TrashIcon
          class="text-white cursor-pointer pointer-events-none"
          :class="{
            ' pointer-events-none opacity-50':
              authUser?.isUser || authUser?.isReadOnly,
          }"
        />
        <transition name="tooltip">
          <div
            v-if="isTooltipDelete"
            class="absolute bottom-9 right-0 z-10 px-5 py-2 text-sm font-medium text-primary transition-opacity duration-300 bg-white rounded-md tooltip"
          >
            حذف
          </div>
        </transition>
      </div>
    </div>
  </LayoutSideBar>

  <!--  modals -->
  <!--  folder-->
  <Modal :is-open="isOpenModal" :title="`ایجاد پوشه جدید`" @close="resetAction">
    <form class="w-full" @submit.prevent="subCreateFolder">
      <div
        class="flex items-center border-b border-primary py-2"
        :class="{ 'border-red-600': errors.length > 0 }"
      >
        <input
          ref="folderInput"
          v-model="form.folderName"
          class="appearance-none bg-transparent border-none focus:border-none w-full text-gray-700 py-1 px-2 leading-tight focus:outline-none shadow-transparent"
          :class="{ 'placeholder-red-400': errors.length > 0 }"
          type="text"
          placeholder="نام پوشه"
        />
        <button
          class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark text-sm border-4 text-white py-1 px-2 rounded disabled:opacity-50 disabled:cursor-not-allowed"
          type="submit"
          :disabled="form.processing"
          v-text="`ایجاد`"
        />
      </div>
      <ul class="list-inside text-sm text-red-600 pt-2">
        <li v-for="(e, i) in errors" :key="i" class="text-start" v-text="e" />
      </ul>
    </form>
  </Modal>
  <!--  file-->
  <Modal
    :is-open="isOpenModalFile"
    :title="`لطفا فایل مورد نظر را انتخاب کنید`"
    @close="resetAction"
  >
    <div class="w-full p-4 transition-all duration-500 ease-in-out">
      <form @submit.prevent="subUploadFile">
        <ul
          v-if="errorsFile.length > 0"
          class="list-inside text-sm text-red-600"
        >
          <li
            v-for="(e, i) in errorsFile"
            :key="i"
            class="text-start"
            v-text="getErrorText(e)"
          />
        </ul>
        <ul
          v-if="errors.length > 0"
          class="list-inside text-sm text-red-600 pt-2"
        >
          <li v-for="(e, i) in errors" :key="i" class="text-start" v-text="e" />
        </ul>
        <div class="flex items-center justify-center mt-3">
          <label
            for="dropzone-file"
            class="flex flex-col items-center justify-center w-[60vw] h-32 border-2 border-primary border-dashed rounded-lg cursor-pointer"
            :class="{
              'border-red-600': errorsFile.includes('files'),
              'border-green-600 bg-green-600/5': isDragover,
            }"
            @drop="drop"
            @dragover.prevent="dragover"
            @dragleave.prevent="dragleave"
          >
            <div class="flex flex-col items-center justify-center w-full">
              <template v-if="!formFile?.file?.name">
                <PlusCircleIcon
                  class="w-20 h-20 text-primary"
                  :class="{
                    '!text-red-600': errorsFile.includes('files'),
                    '!text-green-600 ': isDragover,
                  }"
                />
              </template>
              <template v-else>
                <button
                  type="button"
                  :title="formFile?.file?.name"
                  class="font-medium text-black text-center mt-3 text-sm lg:text-xl truncate md:w-72 w-32"
                  v-text="formFile?.file?.name"
                />
                <p
                  class="text-sm"
                  v-text="`${formFile?.file?.size / 1000000} / مگابایت`"
                />
                <p class="text-sm" v-text="formFile?.file?.type" />
              </template>
            </div>
            <input
              id="dropzone-file"
              ref="fileInput"
              type="file"
              name="file"
              class="hidden"
              accept=".wav,.mp3,.pdf,.aac,.flac,.wma,.jpg,.ogg,.mp4,.jpeg,.tif,.docx,.doc,.xlsx,.xls"
              @change="drop"
            />
          </label>
        </div>

        <!-- checkbox -->
        <div class="flex flex-col justify-start mt-3 md:mt-8">
          <p
            class="text-lg text-right font-normal"
            :class="{ 'text-red-600': errorsFile.includes('tags') }"
          >
            لطفا واحد سازمانی را انتخاب کنید
          </p>

          <div
            v-for="(item, i) in departments"
            :key="i"
            class="flex items-center gap-x-3 mt-3"
          >
            <input
              :id="item.id"
              v-model="formFile.tags"
              :value="item.id"
              type="checkbox"
              class="w-6 h-6 rounded-sm focus:ring-secondPrimary"
            />
            <label
              :for="item.id"
              class="text-base font-medium text-black"
              v-text="item.name"
            />
          </div>

          <button
            class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark text-sm border-4 h-12 shadow-btnUni text-white py-1 px-2 mt-5 rounded-xl cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            type="submit"
            :disabled="formFile.processing"
            v-text="`تایید`"
          />
        </div>
      </form>
    </div>
  </Modal>

  <!--  end modals -->
  <Alert
    class="z-20"
    :title="AlertOption.data"
    :is-open="AlertOption.isAlert"
    :contents-list="AlertOption.dataList"
    :status="AlertOption.status"
    @close="AlertOption.isAlert = false"
  />
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import {
  ArchiveBoxIcon,
  PlusCircleIcon,
  TrashIcon,
} from "@heroicons/vue/24/outline";
import Modal from "../Components/Modal.vue";
import Alert from "../Components/Alert.vue";
import Folder from "../Components/Folder.vue";
import { useForm, usePage } from "@inertiajs/inertia-vue3";
import { Inertia } from "@inertiajs/inertia";
import LayoutSideBar from "../../Layouts/~AppLayoutSideBar.vue";

// eslint-disable-next-line no-undef
defineOptions({
  name: "SideBarDashboard",
});

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  departments: { type: Array, required: true },
  folders: { type: Array, required: true },
  authUser: { type: Object, required: true },
  parentFolderId: { type: String, default: null },
});

const isOpenAdd = ref(true);
const isOpenModal = ref(false);
const isOpenModalFile = ref(false);

const folderInput = ref(null);

const form = useForm({
  folderName: "",
});
const errors = ref([]);
const { url } = usePage();

const isDragover = ref(false);
const format = reactive({
  text: "فایل باید از نوع wav, mp3, aac, flac, wma, ogg, m4a, pdf, wma, mp4, jpeg, tiff, docx, doc, xlsx, xls, pptx, ppt, zip باشد.",
  rex: /(\.wav|\.mp3|\.pdf|\.aac|\.flac|\.wma|\.jpg|\.ogg|\.mp4|\.jpeg|\.tif|\.docx|\.doc|\.xlsx|\.xls|\.pptx|\.ppt|\.zip)$/i,
});
const fileInput = ref(null);
// set Alert
const AlertOption = reactive({
  isAlert: false,
  dataList: [],
  status: "",
  data: "",
});
const formFile = useForm({
  file: null,
  tags: [],
});
const errorsFile = ref([]);
const isTooltipArchive = ref(false);
const isTooltipDelete = ref(false);

// functions
const getErrorText = (error) => {
  switch (error) {
    case "files":
      return "فایل خود را آپلود کنید";
    case "tags":
      return "واحد سازمانی را وارد کنید";
    case "rex":
      return "فایل باید از نوع wav, mp3, aac, flac, wma, ogg, m4a, pdf, wma, mp4, jpeg, tiff, xlsx, xls باشد."; // Added xlsx, xls
    default:
      return error;
  }
};

onMounted(() => {
  // Attach the event listener
  window.addEventListener("click", closeDropdown);
});

// Cleanup when the component is unmounted
onBeforeUnmount(() => {
  window.removeEventListener("click", closeDropdown);
});

function closeDropdown() {
  isOpenAdd.value = true;
}

function openModals(type) {
  if (type === "folder") {
    isOpenModal.value = true;
  }
  if (type === "file") isOpenModalFile.value = true;
  if (folderInput.value) folderInput.value.focus();
}

function setAlerts(isAlert, status, data = "", dataList = []) {
  AlertOption.status = status;
  AlertOption.data = data;
  AlertOption.dataList = dataList;
  AlertOption.isAlert = isAlert;
}

const drop = (e) => {
  e.preventDefault();
  isDragover.value = false;
  const files = e.target.files || e.dataTransfer.files;
  if (!files.length) return;
  addFile(files[0]);
};

const dragover = (e) => {
  e.preventDefault();
  isDragover.value = true;
};

const dragleave = (e) => {
  e.preventDefault();
  isDragover.value = false;
};

function addFile(file) {
  if (format.rex.exec(file?.name)) {
    formFile.file = file;
  } else {
    errorsFile.value.push("rex"); // required
  }
}

function validationFile() {
  errorsFile.value = [];
  if (!formFile.file) {
    errorsFile.value.push("files"); // required
  }
  if (formFile.tags.length === 0) {
    errorsFile.value.push("tags");
  }
  return errorsFile.value.length > 0;
}

watch(
  () => [form.folderName], // Access the 'value' property within the object
  () => {
    errors.value = [];
  },
);
watch(
  () => [formFile.file], // Access the 'value' property within the object
  () => {
    errorsFile.value = errorsFile.value.filter(
      (v) => !["files", "rex"].includes(v),
    );
  },
);
watch(
  () => [formFile.tags], // Access the 'value' property within the object
  () => {
    errorsFile.value = errorsFile.value.filter((v) => v !== "tags");
  },
);

function resetAction() {
  isOpenModal.value = false;
  isOpenModalFile.value = false;
  form.reset();
  formFile.reset();
  if (fileInput.value) fileInput.value.value = null;
}

function validCreateFolder() {
  errors.value = [];
  return !form.folderName
    ? errors.value.push("نام فایل را مشخص کنید .")
    : false;
}

function getSlugFromUrl() {
  const regex = /\/dashboard\/folder\/show\/(\w+)/;
  return (url?.value?.match(regex) || [])[1] || null;
}

function subCreateFolder() {
  if (validCreateFolder()) return;
  setAlerts(false, "success", "", []); // restart Alert
  const choiceRoute = getSlugFromUrl()
    ? "web.user.dashboard.folder.create"
    : "web.user.dashboard.folder.create-root";
  // eslint-disable-next-line no-undef
  form.post(route(choiceRoute, { folderId: getSlugFromUrl() }), {
    replace: true,
    preserveScroll: true,
    onError: (e) => {
      errors.value = Object.values(e).flat();
    },
    onSuccess: () => {
      setAlerts(true, "success", "پوشه مورد نظر ساخته شد.");
      resetAction();
      isOpenAdd.value = true;
    },
  });
}

function subUploadFile() {
  if (validationFile()) return;
  setAlerts(false, "success", "", []); // restart Alert
  const choiceRoute = getSlugFromUrl()
    ? "web.user.dashboard.file.upload"
    : "web.user.dashboard.file.create-root";
  formFile.post(route(choiceRoute, { folderId: getSlugFromUrl() }), {
    replace: true,
    preserveScroll: true,
    onError: (e) => {
      errors.value = Object.values(e).flat();
    },
    onSuccess: () => {
      setAlerts(true, "success", "فایل مورد نظر ساخته شد.");
      resetAction();
      isOpenAdd.value = true;
    },
  });
}

function openArchivePage() {
  Inertia.visit(route("web.user.dashboard.archive-list"), {
    method: "get",
    replace: true,
    preserveState: true, // Add preserveState option
    // onError: (e) => {
    //   console.log(e)
    // }
  });
}

function openDeletePage() {
  Inertia.visit(route("web.user.dashboard.trash-list"), {
    method: "get",
    replace: true,
    preserveState: true, // Add preserveState option
    // onError: (e) => {
    //   console.log(e)
    // }
  });
}
</script>

<style scoped lang="scss">
.shadow-btnUni {
  box-shadow: 0 15px 40px 0 rgba(0, 0, 0, 0.25);
}

.focus\:outline-none:focus {
  box-shadow: none;
}

.tooltip-enter-active {
  transition: all 0.5s ease-out;
}

.tooltip-leave-active {
  transition: all 0.1s cubic-bezier(1, 0.5, 0.8, 1);
}

.tooltip-enter-from,
.tooltip-leave-to {
  transform: translateX(10px);
  opacity: 0;
}
</style>
