<template>
  <div class="min-h-[calc(100vh-12rem)] p-5">
    <div class="flex justify-between items-stretch h-full">
      <div class="w-full mx-5 mt-8">
        <div class="px-2">
          <div class="flex justify-between items-center">
            <div class="flex justify-start items-center gap-x-2 text-gray-500 text-sm">
              <span v-text="`شماره نامه`" />
              <p v-text="id" />
            </div>

            <div class="flex justify-between items-center pt-2">
              <div class="flex items-center gap-x-3">
                <template
                  v-for="(icon, indexIcon) in icons"
                  :key="indexIcon">
                  <button
                    v-if="conditionShowItem(icon.name)"
                    class="relative"
                    @mouseover="isTooltip[indexIcon] = true"
                    @mouseout="isTooltip[indexIcon] =false"
                    @click.stop.prevent="choiceFunction(icon)">
                    <component
                      :is="icon.icon"
                      class="w-5 pointer-events-none relative"
                      :class="conditionItems(icon.name)" />
                    <transition name="tooltip">
                      <div
                        v-if="isTooltip[indexIcon]"
                        class="absolute w-20 bottom-8 right-0 z-10 p-2 text-xs font-medium text-primary transition-opacity
                    duration-300 bg-white rounded-md tooltip"
                        v-text="dictionary(icon.name)" />
                    </transition>
                  </button>
                </template>
              </div>
            </div>
          </div>
          <div
            v-if="referInfo"
            class="flex justify-between items-center">
            <div class="relative flex justify-start items-center gap-x-2 text-gray-500 text-sm">
              <span v-text="`ارجاع شده از`" />
              <button
                class="w-auto px-2 py-1 bg-secondPrimary/10 rounded-lg font-medium text-primary text-xs"
                @mouseover="isTooltipRefer = true"
                @mouseout="isTooltipRefer=false"
                v-text="referInfo?.referrerUser" />
              <transition name="tooltip-refer">
                <div
                  v-if="isTooltipRefer"
                  class=" pointer-events-none absolute w-auto top-8 right-0 z-10 p-2 text-xs font-medium text-gray-500 transition-opacity
                    duration-300 bg-white rounded-md tooltip"
                  v-text="referInfo?.referDescription" />
              </transition>
            </div>
          </div>
          <p
            class="w-auto text-primaryText text-right text-lg font-bold mt-0.5"
            v-text="subject" />
        </div>

        <div class="h-[calc(100vh-21rem)] overflow-auto px-2">
          <div class="mt-6 bg-gray-200/20 rounded-lg p-5">
            <div class="flex justify-between items-center">
              <div class="flex items-center gap-x-2">
                <UserIcon class="w-4 stroke-primaryText" />
                <p
                  class="w-auto text-primaryText text-right text-base font-bold"
                  v-text="sender" />
              </div>

              <p
                dir="ltr"
                class="text-sm font-medium"
                v-text="submittedAt" />
            </div>

            <div class="py-2 text-right">
              <div
                class="text-sm text-right text-primaryText font-bold"
                v-text="text" />

              <div class="flex flex-wrap gap-1 h-auto max-h-16 overflow-y-auto py-1 mt-5">
                <a
                  v-for="(file, fileIndex) in attachment"
                  :key="fileIndex"
                  :href="file?.downloadLink"
                  class="flex items-center gap-x-2 w-auto px-2 py-1 bg-secondPrimary/10 rounded-lg cursor-pointer">
                  <p
                    class="font-medium text-primary text-sm cursor-pointer"
                    v-text="file.fileName" />
                  <ArrowDownTrayIcon class="w-5 cursor-pointer" />
                </a>
              </div>
            </div>
          </div>

          <div
            v-for="(user, i) in replies"
            :key="i"
            class="mt-6 border-b border-primary/10 last:border-b-0 px-5">
            <div class="flex justify-between items-center">
              <div class="flex items-center gap-x-2">
                <UserIcon class="w-4 stroke-primaryText" />
                <p
                  class="w-auto text-primaryText text-right text-base font-medium"
                  v-text="user?.respondingUser" />
              </div>

              <p
                dir="ltr"
                class="text-sm"
                v-text="user?.repliedAt" />
            </div>

            <div class="py-2 text-right">
              <div
                class="text-sm text-right text-primaryText"
                v-text="user?.respondText" />

              <div class="flex flex-wrap gap-1 h-auto max-h-16 overflow-y-auto py-1 mt-5">
                <a
                  v-for="(file, fileIndex) in user?.attachments"
                  :key="fileIndex"
                  :href="file?.downloadLink"
                  class="flex items-center gap-x-2 w-auto px-2 py-1 bg-secondPrimary/10 rounded-lg cursor-pointer">
                  <p
                    class="font-medium text-primary text-sm cursor-pointer"
                    v-text="file.fileName" />
                  <ArrowDownTrayIcon class="w-5 cursor-pointer" />
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-start gap-x-2.5 mt-5 px-5">
          <button
            class="flex justify-center items-center gap-x-2 text-center border border-primary text-sm text-primary w-28
             py-1 rounded-full"
            @click.prevent="openModalReplay">
            <ArrowUturnRightIcon class="w-4" />
            <p
              class="cursor-pointer"
              v-text="`پاسخ`" />
          </button>
          <button
            class="flex justify-center items-center gap-x-2 text-center border border-primary text-sm text-primary w-28
             py-1 rounded-full"
            @click.prevent="openModalForward">
            <p
              class="cursor-pointer"
              v-text="`ارجاع`" />
            <ArrowUturnLeftIcon class="w-4" />
          </button>
        </div>
      </div>

      <div class="flex flex-col h-auto w-1/4 bg-primary/5 p-5 justify-around">
        <div class="border-2 border-dashed border-gray-300 bg-white rounded-lg px-2.5 py-3.5 pb-0">
          <p
            class="w-full font-bold text-right text-primaryText"
            v-text="`توضیحات`" />
          <textarea
            id="description"
            v-model="descriptionLetter"
            rows="3"
            class="w-full text-right text-sm border-0 focus:outline-none focus:ring-0" />
        </div>

        <div class="grid grid-cols-2 items-center gap-y-5">
          <!-- category -->
          <p
            class="font-bold text-right text-primaryText cursor-pointer"
            v-text="`دسته بندی`" />
          <div
            class="flex justify-center items-center w-20 gap-x-2 text-sm rounded-full px-2 py-1 relative cursor-pointer"
            :class="setColorCategory(category)">
            {{ dictionary(category) }}
          </div>
          <!-- priority -->
          <p
            class="font-bold text-right text-primaryText cursor-pointer"
            v-text="`اولویت`" />
          <div
            class="flex justify-center items-center w-20 gap-x-2 text-sm rounded-full px-2 py-1 relative cursor-pointer"
            :class="setColorCategory(priority)">
            {{ dictionary(priority) }}
          </div>

          <!-- duDate -->
          <p
            class="font-bold text-right text-primaryText"
            v-text="`مهلت`" />
          <div class="flex justify-center gap-x-3 rounded-lg w-full bg-secondPrimary/10 text-primaryText p-1">
            <div
              class="w-full text-left"
              v-text="dueDate" />
            <CalendarIcon class="w-6" />
          </div>
        </div>

        <!-- signer -->
        <div class="border-2 border-dashed border-gray-300 bg-white rounded-lg px-2.5 py-3.5">
          <p
            class="w-full font-bold text-right text-primaryText"
            v-text="`امضا کنندگان`" />
          <div class="w-full text-right">
            <div
              v-if="signUsers.length > 0"
              class="flex flex-wrap gap-1 max-h-24 overflow-auto">
              <div
                v-for="(sign , signIndex) in signUsers"
                :key="signIndex"
                class="flex gap-x-2 bg-gray-200 rounded-lg w-auto px-2 py-1">
                <p
                  class="text-primary text-right text-sm"
                  v-text="sign?.userName" />
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="referenceType"
          class="flex items-center gap-x-2 bg-secondPrimary/10 px-2 py-1 rounded-lg"
          @click.prevent="referenceId ? openReferenceType() : null">
          <ReferenceTypeIcon class="w-5" />
          <p
            class="text-sm font-medium text-primaryText cursor-pointer"
            v-text="dictionary(referenceType) + ` به شماره نامه` " />
          <p
            class="text-sm font-medium text-primaryText cursor-pointer"
            v-text="referenceId" />
        </div>

        <div class="flex items-center gap-x-2 px-2 py-1 rounded-lg cursor-pointer hidden">
          <HistoryIcon class="w-5 cursor-pointer" />
          <p
            class="text-sm font-medium text-primaryText cursor-pointer"
            v-text="`تاریخچه`" />
        </div>
      </div>
    </div>

    <!-- modal reminder -->
    <ModalReminder
      :id="id"
      :subject="subject"
      :is-open="isModalReminder"
      @close="isModalReminder = false" />

    <!-- delete modal -->
    <Modal
      :is-open="isOpenModalDelete"
      :is-delete="true"
      @close="closeModal">
      <div class="text-center font-medium text-lg">
        آیا از پاک کردن نامه مطمئن هستید؟
      </div>
      <div class="flex justify-center gap-x-5 mt-5">
        <button
          class="flex-shrink-0 bg-primary hover:bg-primaryDark hover:border-primaryDark text-sm
              text-white w-36 py-2 px-4 rounded-xl"
          type="submit"
          @click="deleteLetters"
          v-text="`تایید`" />
        <button
          class="flex-shrink-0 border border-primary text-primary
              text-sm w-36 py-2 px-4 rounded-xl hover:border-primaryDark hover:text-primaryDark"
          type="submit"
          @click="closeModal"
          v-text="`انصراف`" />
      </div>
    </Modal>

    <!-- archive modal -->
    <Modal
      :is-open="isOpenModalArchive"
      :is-delete="true"
      @close="closeModal">
      <div class="text-right font-medium text-lg">
        آیا از آرشیو کردن نامه مطمئن هستید؟
      </div>
      <div class="flex justify-center gap-x-5 mt-5">
        <button
          class="flex-shrink-0 bg-primary hover:bg-primaryDark hover:border-primaryDark text-sm
              text-white w-36 py-2 px-4 rounded-xl"
          type="submit"
          @click="archiveLetters"
          v-text="`تایید`" />
        <button
          class="flex-shrink-0 border border-primary text-primary
              text-sm w-36 py-2 px-4 rounded-xl hover:border-primaryDark hover:text-primaryDark"
          type="submit"
          @click="closeModal"
          v-text="`انصراف`" />
      </div>
    </Modal>

    <!-- forward modal -->
    <Modal
      modal-size="large"
      :is-open="isModalForward"
      :title="'ارجاع'"
      @close="isModalForward = false">
      <form
        class="flex flex-col w-full gap-y-6"
        @submit.prevent="subForwardLetter">
        <div class="relative w-full px-2.5 border-b border-primary/10">
          <div
            class="flex flex-wrap items-center gap-x-2 cursor-text"
            @click.prevent="focused = true">
            <div
              v-for="(rec, indexUser) in form.usersName"
              :key="indexUser"
              class="flex gap-x-2 w-auto px-2 py-1 bg-secondPrimary/10 rounded-lg">
              <p
                class="font-medium text-primary text-xs"
                v-text="rec" />
              <XMarkIcon
                class="w-3 cursor-pointer"
                @click.prevent="removeUser(indexUser)" />
            </div>
            <input
              id="users"
              ref="inputUsers"
              v-model="receiver"
              type="text"
              autocomplete="off"
              placeholder="گیرندگان"
              class="placeholder-primary bg-transparent border-0 text-primary w-auto focus:outline-none px-0
                focus:bg-transparent focus:border-secondPrimary focus:ring-0 disabled:opacity-50"
              @keydown="removeItemUsers"
              @input="fetchSuggestionUsers">
          </div>
          <!-- Dropdown suggestion user -->
          <div
            v-if="isOpenSuggestionUser"
            class="z-10 shadow w-full absolute right-0">
            <ul>
              <li
                v-for="(sugUser,iSugUser) in sugUsers"
                :key="iSugUser"
                class="cursor-pointer bg-white p-2 text-primaryText border-b border-gray-200"
                :class="{'pointer-events-none' : form.users.find(u=> u === sugUser?.id)}"
                @click.prevent="setSuggestionUser(sugUser?.id ,sugUser?.name )">
                <p
                  class="ms-2 text-sm font-medium cursor-pointer [&>*]:cursor-pointer"
                  :class="{'text-gray-400' : form.users.find(u=> u === sugUser?.id)}">
                  {{ sugUser.name }} <span class="text-gray-500 text-xs">( {{ sugUser.personalId }} )</span>
                </p>
              </li>
            </ul>
          </div>
        </div>

        <div class="flex p-1 items-center w-1/2 justify-between border-b border-primary/5">
          <label
            class="text-gray-500"
            v-text="`مهلت`" />
          <div class="relative pr-5">
            <DatePicker
              v-model="form.dueDate"
              placeholder="1402/09/09"
              class="rounded-lg w-full text-primaryText z-50"
              format="jYYYY/jMM/jDD"
              display-format="jYYYY/jMM/jDD"
              simple
              popover />
            <CalendarIcon class="w-6 absolute top-2 left-2 !text-primaryText" />
          </div>
        </div>

        <div class="border-2 border-dashed border-gray-300 bg-white rounded-lg px-2.5 py-3.5">
          <div class="flex items-center">
            <label
              for="descript"
              class="w-full font-bold text-right text-primaryText">
              <p v-text="`توضیحات`" />
            </label>
            <p class="text-gray-500 text-sm">
              {{ form.description.length }}/250
            </p>
          </div>
          <textarea
            id="descript"
            v-model="form.description"
            rows="2"
            class="w-full border-0 focus:outline-none focus:ring-0"
            @input="characterLimit" />
        </div>

        <div class="flex justify-center gap-x-2">
          <button
            class="text-center bg-primary border-primary mt-10 text-sm border-4 text-white w-36 py-2 px-4 rounded-xl"
            @click.prevent="subForwardLetter"
            v-text="`ارسال`" />
          <Link
            class="flex justify-center items-center bg-white hover:text-white hover:bg-secondPrimary border
                   border-primary hover:border-none mt-10 text-sm text-primary w-36 py-2 px-4 rounded-xl">
            انصراف
          </Link>
        </div>
      </form>
    </Modal>

    <!-- replay modal -->
    <Modal
      modal-size="large"
      :is-open="isModalReplay"
      :title="'پاسخ'"
      @close="isModalReplay = false">
      <form
        class="flex flex-col w-full gap-y-6"
        @submit.prevent="subReplayLetter">
        <div class="flex flex-wrap items-center gap-x-2 cursor-text">
          <p
            class="w-auto px-2 py-1 bg-secondPrimary/10 rounded-lg font-medium text-primary text-xs"
            v-text="sender" />
        </div>
        <div class="border-2 border-dashed border-gray-300 bg-white rounded-lg px-2.5 py-3.5">
          <label
            for="replay"
            class="w-full font-bold text-right text-primaryText">
            <p v-text="`متن پاسخ`" />
          </label>
          <textarea
            id="replay"
            v-model="formReplay.text"
            rows="8"
            class="w-full border-0 focus:outline-none focus:ring-0" />
          <div class="flex flex-wrap gap-1 h-auto max-h-16 overflow-y-auto py-1">
            <div
              v-for="(file, indexFile) in formReplay.attachments"
              :key="indexFile"
              class="flex gap-x-2 w-auto px-2 py-1 bg-secondPrimary/10 rounded-lg">
              <p
                class="font-medium text-primary text-xs"
                v-text="file?.name" />
              <span
                class="font-medium text-primary text-xs"
                v-text="convertFileSize(file?.size)" />
              <XMarkIcon
                class="w-3 cursor-pointer"
                @click.prevent="removeAttachFile(indexFile)" />
            </div>
          </div>

          <div class="flex items-center">
            <button
              class="relative"
              @mouseover="isTooltipAttach= true"
              @mouseout="isTooltipAttach =false"
              @click.stop.prevent="openAttachFile">
              <PaperClipIcon class="w-5 pointer-events-none relative" />
              <transition name="tooltip">
                <div
                  v-if="isTooltipAttach"
                  class="absolute w-20 bottom-8 right-0 z-10 p-2 text-xs font-medium text-primary transition-opacity
                    duration-300 bg-white rounded-md tooltip"
                  v-text="`الصاق`" />
              </transition>
            </button>
            <input
              id="dropzone-file"
              ref="attach"
              type="file"
              class="hidden"
              multiple
              @change="UploadFiles">
          </div>
        </div>

        <div class="flex justify-center gap-x-2">
          <button
            class="text-center bg-primary border-primary mt-10 text-sm border-4 text-white w-36 py-2 px-4 rounded-xl"
            @click.prevent="subReplayLetter"
            v-text="`ارسال`" />
          <Link
            class="flex justify-center items-center bg-white hover:text-white hover:bg-secondPrimary border
                   border-primary hover:border-none mt-10 text-sm text-primary w-36 py-2 px-4 rounded-xl">
            انصراف
          </Link>
        </div>
      </form>
    </Modal>

    <Alert
      class="z-20"
      :title="AlertOption.data"
      :is-open="AlertOption.isAlert"
      :contents-list="AlertOption.dataList"
      :status="AlertOption.status"
      @close="AlertOption.isAlert=false" />
  </div>
</template>

<script setup>
import layout from '../../../Layouts/~AppLayout.vue'
import {
  ArchiveBoxIcon,
  ArrowDownTrayIcon,
  CalendarIcon,
  DocumentCheckIcon,
  TrashIcon,
  UserIcon,
  XMarkIcon,
  ArrowUturnLeftIcon,
  ArrowUturnRightIcon,
  PaperClipIcon
} from '@heroicons/vue/24/outline'
import { onMounted, reactive, ref } from 'vue'
import ReminderIcon from '../../Components/icon/ReminderIcon.vue'
import { Link, useForm } from '@inertiajs/inertia-vue3'
import Alert from '../../Components/Alert.vue'
import ModalReminder from '../../Components/ModalReminder.vue'
import Modal from '../../Components/Modal.vue'
import HistoryIcon from '../../Components/icon/HistoryIcon.vue'
import ReferenceTypeIcon from '../../Components/icon/ReferenceTypeIcon.vue'
import DatePicker from 'vue3-persian-datetime-picker'
import SignIcon from '../../Components/icon/SignIcon.vue'
import { dictionary } from '../../../globalFunction/dictionary.js'
import { Inertia } from '@inertiajs/inertia'
import { useFocus } from '@vueuse/core'
import debounce from 'lodash/debounce'
import axios from 'axios'

defineOptions({
  name: 'Letter',
  layout
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, required: true },
  attachment: { type: Array, required: true },
  description: { type: String, required: true },
  dueDate: { type: String, required: true },
  id: { type: Number, required: true },
  priority: { type: String, required: true },
  referenceType: { type: String, required: true },
  referenceId: { type: Number, required: true },
  replies: { type: Array, required: true },
  sender: { type: String, required: true },
  senderUser: { type: Number, required: true },
  subject: { type: String, required: true },
  status: { type: String, required: true },
  submittedAt: { type: String, required: true },
  text: { type: String, required: true },
  category: { type: String, required: true },
  signUsers: { type: Array, required: true },
  referInfo: { type: Object, required: true }
})

const isSaveMessage = ref(false)
const isTooltip = ref([])
const isModalReminder = ref(false)
const isModalForward = ref(false)
const isModalReplay = ref(false)
const isSignMe = ref(false)
const isShowSign = ref(false)
const isOpenModalDelete = ref(false)
const isOpenModalArchive = ref(false)
const isOpenSuggestionUser = ref(false)
const isTooltipAttach = ref(false)
const isTooltipRefer = ref(false)

const attach = ref(null)
const limitFile = 3
const inputUsers = ref(null)
const { focused } = useFocus(inputUsers)

const receiver = ref('')
const sugUsers = ref([])
const descriptionLetter = ref('')
const textLetter = ref('')
const icons = [
  {
    icon: ReminderIcon,
    name: 'reminder'
  },
  {
    icon: DocumentCheckIcon,
    name: 'saveMessage'
  },
  {
    icon: SignIcon,
    name: 'signMe'
  },
  {
    icon: ArchiveBoxIcon,
    name: 'Archive'
  },
  {
    icon: TrashIcon,
    name: 'Delete'
  }
]
const form = useForm({
  usersName: [],
  users: [],
  dueDate: '',
  description: ''
})
const formReplay = useForm({
  text: '',
  attachments: [],
  users: [props.senderUser]
})
const AlertOption = reactive({
  isAlert: false,
  dataList: [],
  status: '',
  data: ''
})
const errors = ref([])

onMounted(() => {
  descriptionLetter.value = props.description
  textLetter.value = props.text

  // checking for sign in user
  const usersIds = props.signUsers.map(({ id }) => id)
  isShowSign.value = usersIds.includes(props.authUser.id)
})

const setColorCategory = (item) => {
  return {
    'bg-blue-100 text-blue-900': item === 'NORMAL',
    'bg-red-100 text-red-900': item === 'SECRET' || item === 'INSTANT',
    'bg-orange-100 text-orange-900 ': item === 'CONFIDENTIAL' || item === 'IMMEDIATELY'
  }
}

const conditionItems = (name) => {
  if (name === 'saveMessage') return isSaveMessage.value ? 'text-green-500' : 'text-primaryText'
  if (name === 'Delete') return 'stroke-red-500'
  if (isShowSign.value && name === 'signMe') {
    const checkSignMe = props.signUsers.filter(({ id }) => id === props.authUser.id)
    return checkSignMe[0].signedAt ? 'stroke-blue-500' : 'stroke-primaryText'
  }
}

const conditionShowItem = (name) => (!(name === 'signMe' && !isShowSign.value))

function choiceFunction (item) {
  switch (item.name) {
    case 'reminder' :
      openModalReminder()
      break
    case 'saveMessage' :
      saveMessage()
      break
    case 'signMe' :
      signMessage()
      break
    case 'Archive' :
      openModalArchiveLetter()
      break
    case 'Delete' :
      openModalDeleteLetter()
      break
    default:
      return null
  }
}

function closeModal () {
  isOpenModalDelete.value = false
  isOpenModalArchive.value = false
}

function openModalForward () {
  isModalForward.value = true
}

function openModalReplay () {
  isModalReplay.value = true
}

function addFile (files) {
  // set 3 file
  const filesBigSize = []
  for (let i = 0; i < files.length; i++) {
    // validation size files
    if (files[i].size < 5_000_000) {
      formReplay.attachments.push(files[i])
    } else {
      filesBigSize.push(files[i].name)
      setAlerts(true, 'error', 'حداکثر حجم هر فایل الصاقی ۵ مگابایت میتواند باشد .', filesBigSize)
    }
  }

  // validation limit number file attachments
  if (formReplay.attachments.length > limitFile) {
    setAlerts(true, 'error', 'حداکثر ۳ فایل می‌توانید آپلود بفرمایید .')
    formReplay.attachments = formReplay.attachments.slice(-3)
  }
}

function UploadFiles (e) {
  e.preventDefault()
  const files = e.target.files || e.dataTransfer.files
  if (!files.length) return
  addFile(files)
}

const convertFileSize = (fileSize) => {
  const convertSize = fileSize / 1000000
  const formattedSize = convertSize.toFixed(2)
  return `(${formattedSize} مگابایت)`
}

function removeAttachFile (i) {
  formReplay.attachments.splice(i, 1)
}

function openAttachFile () {
  attach.value.click()
}

const fetchSuggestionUsers = debounce(async () => {
  if (receiver.value?.length < 2 || !receiver.value) {
    sugUsers.value = []
    return
  }
  try {
    const res = await axios.post(route('web.user.api.users'), { identifier: receiver.value })
    if (res.status === 200) {
      sugUsers.value = res.data
      isOpenSuggestionUser.value = true
    }
  } catch (e) {
    console.error('Error fetching suggestion users:', e)
  }
}, 500)

function removeItemUsers (e) {
  if (e.key === 'Backspace') {
    if (receiver.value && receiver.value?.length > 0) return
    receiver.value = form.usersName.at(-1)
    removeUser(form.users.length - 1)
  }
}

function removeUser (i) {
  form.users.splice(i, 1)
  form.usersName.splice(i, 1)
}

function setUsers (id, name) {
  const existingUser = form.users.find((u) => u === id)
  if (existingUser) return
  form.users.push(id)
  form.usersName.push(name)
}

function setSuggestionUser (id, name) {
  setUsers(id, name)
  isOpenSuggestionUser.value = false
  focused.value = true
  receiver.value = ''
}

function openModalReminder () {
  isModalReminder.value = true
}

function openModalArchiveLetter () {
  isOpenModalArchive.value = true
}

function archiveLetters () {
  Inertia.visit(route('web.user.cartable.archive.action'), {
    method: 'post',
    data: { letters: [props.id] },
    replace: true,
    preserveState: true, // Add preserveState option
    onSuccess: () => {
      isOpenModalArchive.value = false
    }
  })
}

function openModalDeleteLetter () {
  isOpenModalDelete.value = true
}

function deleteLetters () {
  Inertia.visit(route('web.user.cartable.temp-delete.action'), {
    method: 'post',
    data: { letters: [props.id] },
    replace: true,
    preserveState: true, // Add preserveState option
    onSuccess: () => {
      isOpenModalDelete.value = false
    }
  })
}

function signMessage () {
  Inertia.visit(route('web.user.cartable.sign.action', { letter: props?.id }), {
    method: 'post',
    replace: true,
    preserveState: true, // Add preserveState option
    onError: (e) => {
      errors.value = Object.values(e).flat()
      setAlerts(true, 'error', 'نامه امضا نشد.', errors.value)
    },
    onSuccess: () => {
      isSignMe.value = true
      setAlerts(true, 'info', 'نامه امضا شد.')
    }
  })
}

function saveMessage () {
  isSaveMessage.value = true
  setAlerts(true, 'success', 'پیام با موفقیت ذخیره شد.')
}

function resetAction () {
  isSaveMessage.value = false
  isSignMe.value = false
  isModalForward.value = false
  isModalReplay.value = false
  form.reset()
  formReplay.reset()
}

function setAlerts (isAlert, status, data = '', dataList = []) {
  AlertOption.status = status
  AlertOption.data = data
  AlertOption.dataList = dataList
  AlertOption.isAlert = isAlert
}

function characterLimit () {
  if (form.description.length > 250) form.description = form.description.slice(0, 250)
}

function openReferenceType () {
  Inertia.visit(route('web.user.cartable.letter.show', { letter: props.referenceId }), {
    method: 'get'
  })
}

function subForwardLetter () {
  form.post(route('web.user.cartable.refer.action', { letter: props.id }), {
    replace: true,
    preserveScroll: true,
    onError: (e) => {
      errors.value = Object.values(e).flat()
    },
    onSuccess: () => {
      setAlerts(true, 'success', 'نامه ارجاع شد.')
      resetAction()
    }
  })
}

function subReplayLetter () {
  formReplay.post(route('web.user.cartable.reply.action', { letter: props.id }), {
    replace: true,
    preserveScroll: true,
    onError: (e) => {
      errors.value = Object.values(e).flat()
    },
    onSuccess: () => {
      setAlerts(true, 'success', 'پاسخ نامه ارسال شد.')
      resetAction()
    }
  })
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

.tooltip-refer-enter-active {
  transition: all 0.5s ease-out;
}

.tooltip-refer-leave-active {
  transition: all 0.1s cubic-bezier(1, 0.5, 0.8, 1);
}

.tooltip-refer-enter-from,
.tooltip-refer-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}

.v3ti {
  border: none;
  outline: none;
  box-shadow: none;

  & .v3ti-tag {
    margin: 12px;
  }

}

</style>
