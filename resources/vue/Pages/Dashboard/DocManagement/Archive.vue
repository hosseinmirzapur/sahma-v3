<template>
  <div class="p-5 min-h-[calc(100vh-9rem)]">
    <h3
      class="text-primaryText text-lg px-5"
      v-text="status==='trash' ? `حذف` : `آرشیو` " />
    <div
      v-if="folders?.length>0 || files?.length>0"
      class="flex flex-wrap gap-x-10">
      <template v-if="folders?.length>0">
        <div
          v-for="(doc, i) in folders"
          :key="i"
          class="relative flex items-center gap-x-5 cursor-pointer border border-transparent rounded rounded-xl">
          <div class="flex justify-center items-center w-14 h-14 bg-primary/5 text-gray-600 rounded-xl">
            <FolderIcon class="w-8" />
          </div>
          <p
            class="text-lg cursor-pointer select-none"
            v-text="doc.name" />
          <ArrowUturnLeftIcon
            class="w-6 hover:border hover:rounded-full hover:shadow-cardUni"
            @click="openModalRestore(doc, 'folder')" />
          <TrashIcon
            class="w-6 hover:border hover:rounded-full hover:shadow-cardUni text-red-600"
            :class="authUser?.isSuperAdmin ? '' :'hidden'"
            @click="openModalDelete(doc, 'folder')" />
        </div>
      </template>

      <template v-if="files?.length>0">
        <div
          v-for="(doc, i) in files"
          :key="i"
          class="relative flex items-center gap-x-5 cursor-pointer border border-transparent hover:border  rounded rounded-xl">
          <div
            class="flex justify-center items-center w-14 h-14 rounded-xl"
            :class="setBgFiles(doc?.type)">
            <DocumentIcon
              v-if="doc?.type === 'pdf' || doc?.type === 'image'"
              class="w-8" />
            <MicrophoneIcon
              v-if="doc?.type === 'voice'"
              class="w-8" />
            <VideoCameraIcon
              v-if="doc?.type === 'video'"
              class="w-8" />
          </div>
          <p
            class="text-lg"
            v-text="doc.name" />
          <ArrowUturnLeftIcon
            class="w-6 hover:border hover:rounded-full hover:shadow-cardUni"
            @click="openModalRestore(doc, 'file')" />
          <TrashIcon
            class="w-6 hover:border hover:rounded-full hover:shadow-cardUni text-red-600"
            :class="authUser?.isSuperAdmin ? '' :'hidden'"
            @click="openModalDelete(doc, 'file')" />
        </div>
      </template>

      <!--restore modal-->
      <Modal
        :is-open="isModalRestore"
        :title="'بازگردانی'"
        @close="resetAction">
        <div class="w-full">
          <p v-if="currFolder">
            آیامطمئن هستید پوشه <span class="font-bold px-2"> {{ currFolder?.name }} </span> بازگردانی شود؟
          </p>
          <p v-if="currFile">
            آیامطمئن هستید فایل<span class="font-bold px-2"> {{ currFile?.name }} </span>بازگردانی شود؟
          </p>
          <button
            class="w-full flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark
                 text-sm border-4 text-white py-1 px-2 rounded-xl mt-5"
            @click="subRestore"
            v-text="`بازگردانی`" />
        </div>
      </Modal>

      <!--delete modal-->
      <Modal
        :is-open="isModalDelete"
        :title="'حذف کردن'"
        @close="resetAction">
        <div class="w-full">
          <p v-if="currFolder">
            آیامطمئن هستید پوشه <span class="font-bold px-2"> {{ currFolder?.name }} </span> حذف شود؟
          </p>
          <p v-if="currFile">
            آیامطمئن هستید فایل<span class="font-bold px-2"> {{ currFile?.name }} </span>حذف شود؟
          </p>
          <button
            class="w-full flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark
                 text-sm border-4 text-white py-1 px-2 rounded-xl mt-5"
            @click="subDelete"
            v-text="`حذف`" />
        </div>
      </Modal>
    </div>
    <div
      v-else
      class="h-[calc(100vh-20rem)] flex justify-center item center">
      <div class="w-1/2 rounded-xl flex flex-col items-center gap-y-8 text-lg text-base p-5 m-auto">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="181"
          height="204"
          viewBox="0 0 181 204"
          fill="none">
          <ellipse
            opacity="0.1"
            cx="90.5"
            cy="104"
            rx="90.5"
            ry="91"
            fill="#2A3875" />
          <rect
            x="65.223"
            y="47.1755"
            width="96.4336"
            height="134.713"
            rx="7"
            transform="rotate(2.5 65.223 47.1755)"
            fill="#FAFAFA"
            stroke="#8AA6FF"
            stroke-width="2" />
          <rect
            x="9.09385"
            y="39.8598"
            width="100.434"
            height="138.713"
            rx="9"
            transform="rotate(-7.5 9.09385 39.8598)"
            fill="#FAFAFA"
            stroke="#8AA6FF"
            stroke-width="2" />
          <path
            d="M18.2126 40.1722L99.941 29.4125C104.048 28.8718 107.815 31.7627 108.356 35.8694L111.351 58.6171L14.7506 71.3348L11.7558 48.587C11.2151 44.4803 14.1059 40.7129 18.2126 40.1722Z"
            fill="#FAFAFA"
            stroke="#8AA6FF" />
          <rect
            x="129.03"
            y="31.9727"
            width="4.1014"
            height="13.6713"
            rx="2.0507"
            transform="rotate(82.5 129.03 31.9727)"
            fill="#8AA6FF" />
          <rect
            x="91.5762"
            y="9.32617"
            width="4.1014"
            height="13.6713"
            rx="2.0507"
            transform="rotate(-7.5 91.5762 9.32617)"
            fill="#8AA6FF" />
          <rect
            x="115.606"
            y="13.0566"
            width="4.1014"
            height="13.6713"
            rx="2.0507"
            transform="rotate(37.5 115.606 13.0566)"
            fill="#8AA6FF" />
          <rect
            x="24.5215"
            y="76.0684"
            width="79.2937"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 24.5215 76.0684)"
            fill="#EAEBF1" />
          <rect
            x="28.0908"
            y="103.178"
            width="79.2937"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 28.0908 103.178)"
            fill="#EAEBF1" />
          <rect
            x="31.6602"
            y="130.287"
            width="79.2937"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 31.6602 130.287)"
            fill="#EAEBF1" />
          <rect
            x="25.9492"
            y="86.9121"
            width="28.7098"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 25.9492 86.9121)"
            fill="#D1D8FF" />
          <rect
            x="29.5186"
            y="114.021"
            width="28.7098"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 29.5186 114.021)"
            fill="#D1D8FF" />
          <rect
            x="33.0869"
            y="141.129"
            width="28.7098"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 33.0869 141.129)"
            fill="#D1D8FF" />
        </svg>
        <p class="text-center text-primary/70">
          در حال حاضر سندی
          <span v-text="status==='trash' ? `حذف` : `آرشیو` " />
          نشده است
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  DocumentIcon,
  FolderIcon,
  MicrophoneIcon,
  VideoCameraIcon,
  ArrowUturnLeftIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import layout from '../../../Layouts/~AppLayout.vue'
import Modal from '../../Components/Modal.vue'
import { ref } from 'vue'
import { useForm } from '@inertiajs/inertia-vue3'

defineOptions({
  name: 'Archive',
  layout
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, required: true },
  folders: { type: Object, required: true },
  files: { type: Object, required: true },
  status: { type: String, default: 'archive' }
})

const form = useForm({
  folders: [],
  files: []
})
const isModalRestore = ref(false)
const isModalDelete = ref(false)
const currFolder = ref(null)
const currFile = ref(null)

function setBgFiles (type) {
  switch (type) {
    case 'pdf':
    case 'image':
      return 'bg-pdf/5 text-pdf'
    case 'voice':
      return 'bg-voice/5 text-voice'
    case 'video':
      return 'bg-video/5 text-video'
    default:
      return type
  }
}

function openModalDelete (doc, type) {
  if (type === 'folder') currFolder.value = doc
  if (type === 'file') currFile.value = doc
  isModalDelete.value = true
}

function openModalRestore (doc, type) {
  if (type === 'folder') currFolder.value = doc
  if (type === 'file') currFile.value = doc
  isModalRestore.value = true
}

function resetAction () {
  isModalRestore.value = false
  isModalDelete.value = false
  currFolder.value = null
  currFile.value = null
  form.reset()
}

function setDataForm () {
  if (currFile.value) form.files.push(currFile.value?.id)
  if (currFolder.value) form.folders.push(currFolder.value?.id)
}

function subRestore () {
  const routeItem = props.status === 'trash' ? 'web.user.dashboard.trash-retrieve' : 'web.user.dashboard.archive-retrieve'
  setDataForm()

  form.post(route(routeItem), {
    replace: true,
    preserveState: false,
    onSuccess: () => {
      resetAction()
    }
  })
}

function subDelete () {
  const routeItem = props.status === 'trash' ? 'web.user.dashboard.permanent-delete' : 'web.user.dashboard.trash-action'
  setDataForm()

  form.post(route(routeItem), {
    replace: true,
    preserveState: false,
    onSuccess: () => {
      resetAction()
    }
  })
}

</script>
