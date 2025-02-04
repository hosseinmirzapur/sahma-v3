<template>
  <div class="min-h-[calc(100vh-9rem)] p-5">
    <div class="flex justify-between items-stretch h-full">
      <div class="w-full mx-7">
        <div class="border border-primary/10 rounded-lg">
          <p
            class="bg-primary text-white font-bold text-base py-2 px-2.5 rounded-t-lg"
            v-text="`نامه جدید`" />

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
          <!-- end Dropdown suggestion user -->

          <div class="px-2.5 border-b border-primary/10 flex gap-x-2 items-center w-full">
            <label
              for="subject"
              class="text-sm font-medium text-primary w-20"
              v-text="`موضوع`" />
            <input
              id="subject"
              v-model="form.subject"
              type="text"
              class="placeholder-primary bg-transparent border-0 text-primary
                 w-full px-4 focus:outline-none focus:bg-transparent focus:border-secondPrimary focus:ring-0
                 disabled:opacity-50"
              @input="characterLimit">
          </div>

          <div class="px-2.5 text-sm text-right border-b border-primary/10">
            <textarea
              v-model="form.text"
              rows="20"
              class="w-full h-full border-0 px-0 focus:outline-none focus:ring-0 resize-none" />
            <div class="flex flex-wrap gap-1 h-auto max-h-16 overflow-y-auto py-1">
              <div
                v-for="(file, indexFile) in form.attachments"
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
          </div>

          <div class="flex items-center gap-x-3 py-2 px-2.5 rounded-b-lg">
            <template
              v-for="(icon, indexIcon) in icons"
              :key="indexIcon">
              <button
                class="relative"
                @mouseover="isTooltip[indexIcon] = true"
                @mouseout="isTooltip[indexIcon] =false"
                @click.stop.prevent="choiceFunction(icon)">
                <component
                  :is="icon.icon"
                  class="w-5 pointer-events-none relative "
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
            <input
              id="dropzone-file"
              ref="attach"
              type="file"
              class="hidden"
              multiple
              @change="UploadFiles">
          </div>
        </div>
      </div>

      <div class="flex flex-col h-auto w-1/3 bg-primary/5 p-5 gap-y-5 justify-around">
        <div class="border-2 border-dashed border-gray-300 bg-white rounded-lg px-2.5 py-3.5">
          <label
            for="description"
            class="w-full font-bold text-right text-primaryText">
            <p v-text="`توضیحات`" />
          </label>
          <textarea
            id="description"
            v-model="form.description"
            rows="2"
            class="w-full border-0 focus:outline-none focus:ring-0 resize-none" />
        </div>

        <div class="grid grid-cols-2 items-center gap-y-5">
          <!-- category -->
          <p
            class="font-bold text-right text-primaryText cursor-pointer"
            @click.stop.prevent="isOpenCategory=!isOpenCategory"
            v-text="`دسته بندی`" />
          <div>
            <div
              class="flex justify-center items-center w-28 gap-x-2 text-sm rounded-full px-2 py-1 relative cursor-pointer"
              :class="setColorCategory(form.category)"
              @click.stop.prevent="isOpenCategory=!isOpenCategory">
              <!--              <span-->
              <!--                class="w-2 h-2 rounded-full"-->
              <!--                :class="setColorBadge(form.category)" />-->
              {{ dictionary(form.category) }}
            </div>
            <!-- Dropdown menu -->
            <OnClickOutside @trigger="isOpenCategory=false">
              <div
                v-if="isOpenCategory"
                class="z-10 shadow w-28 absolute">
                <ul>
                  <li
                    v-for="(cat, indexCat) in categories"
                    :key="indexCat"
                    class="cursor-pointer bg-white p-2 text-primaryText border-b border-gray-200"
                    @click.prevent="subCategory(cat)">
                    <p
                      class="ms-2 text-sm font-medium cursor-pointer"
                      v-text="dictionary(cat)" />
                  </li>
                </ul>
              </div>
            </OnClickOutside>
          </div>
          <!-- priority -->
          <p
            class="font-bold text-right text-primaryText cursor-pointer"
            @click.stop.prevent="isOpenPriority=!isOpenPriority"
            v-text="`اولویت`" />
          <div>
            <div
              class="flex justify-center items-center w-28 gap-x-2 text-sm rounded-full px-2 py-1 relative cursor-pointer"
              :class="setColorCategory(form.priority)"
              @click.stop.prevent="isOpenPriority=!isOpenPriority">
              {{ dictionary(form.priority) }}
            </div>
            <!-- Dropdown menu -->
            <OnClickOutside @trigger="isOpenPriority=false">
              <div
                v-if="isOpenPriority"
                class="z-10 shadow w-28 absolute">
                <ul>
                  <li
                    v-for="(pri, indexPriority) in priorities"
                    :key="indexPriority"
                    class="cursor-pointer bg-white p-2 text-primaryText border-b border-gray-200"
                    @click.prevent="subPriority(pri)">
                    <p
                      class="ms-2 text-sm font-medium cursor-pointer"
                      v-text="dictionary(pri)" />
                  </li>
                </ul>
              </div>
            </OnClickOutside>
          </div>

          <!-- dueDate -->
          <p
            class="font-bold text-right text-primaryText"
            v-text="`مهلت`" />
          <div class="relative">
            <DatePicker
              v-model="form.dueDate"
              class="rounded-lg [&>*>input]:w-full bg-secondPrimary/10 text-primaryText "
              format="jYYYY-jMM-jDD"
              placeholder="1402/09/09"
              display-format="jYYYY/jMM/jDD"
              simple
              popover />
            <CalendarIcon class="w-6 absolute top-2 left-2 !text-primaryText" />
          </div>
          <!-- signer -->
          <label
            for="signs"
            class="font-bold text-right text-primaryText w-32"
            v-text="`امضا کنندگان`" />
          <div class="relative flex items-center">
            <input
              id="signs"
              v-model="sign"
              type="text"
              autocomplete="off"
              class="border-0 rounded-lg w-full bg-secondPrimary/10 text-primaryText w-full px-4 focus:outline-none
              focus:border-secondPrimary focus:ring-0 disabled:opacity-50"
              @click.prevent="isModalSign= true">
            <PlusIcon class="w-6 absolute top-2 left-2 text-primaryText cursor-pointer" />
            <!-- Dropdown menu -->
            <OnClickOutside @trigger="isModalSign=false">
              <div
                v-if="isModalSign"
                class="absolute top-12 left-0 z-10 bg-white rounded-lg shadow w-60">
                <ul
                  v-if="form.users.length > 0"
                  class="max-h-48 overflow-y-auto text-sm text-gray-700 ">
                  <li
                    v-for="(usr , indexUsr) in signFilter"
                    :key="indexUsr">
                    <div class="flex items-center ps-4 rounded hover:bg-primaryDark/5">
                      <input
                        :id="`checkbox-${indexUsr}`"
                        v-model="form.signs"
                        type="checkbox"
                        :value="form.users[indexUsr]"
                        class="w-4 h-4 text-blue-600 bg-gray-100
                      border-gray-300 rounded focus:ring-blue-500">
                      <label
                        :for="`checkbox-${indexUsr}`"
                        class="w-full p-3 ms-2 text-sm font-medium text-gray-900
                      rounded select-none cursor-pointer">
                        <!--                        <span class="text-xs text-gray-600">({{ form.users[indexUsr] }})</span>-->
                        {{ usr }}
                      </label>
                    </div>
                  </li>
                </ul>
                <ul
                  v-else
                  class="max-h-48 overflow-y-auto text-sm text-gray-700 ">
                  <span> لطفاً گیرندگان نامه را مشخص کنید</span>
                </ul>
              </div>
            </OnClickOutside>
          </div>
        </div>
        <div
          v-if="form.signs.length>0"
          class="flex flex-wrap gap-1 border-2 border-dashed border-gray-300 bg-white rounded-lg px-2.5 py-3.5 max-h-24
          overflow-auto">
          <div
            v-for="(signer, indexSign) in form.signs"
            :key="indexSign"
            class="flex gap-x-2 bg-gray-200 rounded-lg w-auto px-2 py-1">
            <p
              class="text-primary text-right text-sm"
              v-text="findNameSignsInUsers(signer)" />
            <XMarkIcon
              class="w-3 cursor-pointer"
              @click.prevent="removeSigners(indexSign)" />
          </div>
        </div>
        <div class="flex justify-center gap-x-2 w-full">
          <button
            class="text-center bg-primary border-primary mt-10 text-sm border-4 w-full text-white py-2 px-4 rounded-xl"
            @click.prevent="subCreateLetter"
            v-text="`ارسال`" />
        </div>
      </div>
    </div>

    <!-- referenceType modal -->
    <Modal
      modal-size="large"
      :is-open="isModalReferenceType"
      :title="'عطف / پیرو'"
      @close="isModalReferenceType = false">
      <form class="flex flex-col w-full gap-y-6">
        <div class="flex justify-start items-center gap-x-5">
          <div class="flex items-center">
            <input
              id="REFERENCE"
              v-model="form.referenceType"
              class="hidden peer"
              type="radio"
              name="REFERENCE"
              value="REFERENCE">
            <label
              class="mt-px inline-block cursor-pointer text-sm px-4 py-2 rounded-lg peer-checked:bg-blue-200 peer-checked:text-blue-700"
              for="REFERENCE"
              v-text="`عطف`" />
          </div>
          <div class="flex items-center">
            <input
              id="FOLLOW"
              v-model="form.referenceType"
              class="hidden peer"
              type="radio"
              name="FOLLOW"
              value="FOLLOW">
            <label
              class="mt-px inline-block cursor-pointer text-sm px-4 py-2 rounded-lg peer-checked:bg-blue-200 peer-checked:text-blue-700"
              for="FOLLOW"
              v-text="`پیرو`" />
          </div>
        </div>

        <div class="relative flex items-center w-full border-b border-primary/10">
          <div class="flex flex-wrap items-center gap-x-2 cursor-text m-1">
            <div
              v-if="form.referenceId"
              class="flex gap-x-2 w-auto px-2 py-1 bg-secondPrimary/10 rounded-lg">
              <span class="font-medium text-primary text-xs">({{ form.referenceId }})</span>
              <p
                class="font-medium text-primary text-xs"
                v-text="form.referenceName" />
              <XMarkIcon
                class="w-3 cursor-pointer"
                @click.prevent="removeLetterInReference()" />
            </div>
            <input
              v-if="!form.referenceId"
              id="inputFindLetter"
              v-model="inputFindLetter"
              type="text"
              placeholder="شماره نامه / موضوع نامه"
              class="col-span-1 placeholder-gray-500 bg-transparent border-0 text-primary p-1 w-full focus:outline-none
             focus:bg-transparent focus:ring-0 disabled:opacity-50 "
              @input="fetchSuggestionLetter">
          </div>
          <!-- Dropdown suggestion id -->
          <div
            v-if="isOpenSuggestionId"
            class="z-50 shadow w-full absolute top-8 right-0 bg-white">
            <ul>
              <li
                v-for="(sugId,iSugId) in sugFindLetter"
                :key="iSugId"
                class="cursor-pointer bg-white p-2 text-primaryText border-b border-gray-200 hover:bg-primaryDark/5"
                @click.prevent="setSuggestionId(sugId)">
                <div
                  class="flex items-center ms-2 text-sm font-medium cursor-pointer
                  [&>*]:cursor-pointer [&>*]:text-gray-600 justify-between">
                  <p>{{ sugId?.id }}</p>
                  <p>{{ sugId?.subject }}</p>
                  <p>{{ sugId?.subject }}</p>
                  <p>{{ sugId?.subject }}</p>
                  <p>{{ sugId?.subject }}</p>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <div class="flex justify-center gap-x-2">
          <button
            class="text-center bg-primary border-primary mt-10 text-sm border-4 text-white w-36 py-2 px-4 rounded-xl"
            @click.prevent="subReferenceType"
            v-text="`تایید`" />
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
      :timer="3500"
      @close="AlertOption.isAlert=false" />
  </div>
</template>

<script setup>
import layout from '../../../Layouts/~AppLayout.vue'
import { CalendarIcon, DocumentCheckIcon, PaperClipIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { reactive, ref, watch, computed, onMounted } from 'vue'
import debounce from 'lodash/debounce'
import ReferenceTypeIcon from '../../Components/icon/ReferenceTypeIcon.vue'
import DatePicker from 'vue3-persian-datetime-picker'
import { Link, useForm, usePage } from '@inertiajs/inertia-vue3'
import Alert from '../../Components/Alert.vue'
import Modal from '../../Components/Modal.vue'
import { dictionary } from '../../../globalFunction/dictionary.js'
import { OnClickOutside } from '@vueuse/components'
import { useFocus } from '@vueuse/core'
import axios from 'axios'

defineOptions({
  name: 'CreateLetter',
  layout
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  attachment: { type: Boolean, required: true },
  description: { type: String, required: true },
  dueDate: { type: String, required: true },
  id: { type: Number, required: true },
  priority: { type: String, required: true },
  referenceType: { type: String, required: true },
  referenceId: { type: Number, required: true },
  sender: { type: String, required: true },
  subject: { type: String, required: true },
  status: { type: String, required: true },
  submittedAt: { type: String, required: true },
  text: { type: String, required: true },
  category: { type: String, required: true },
  signUsers: { type: Array, required: true }
})

const isOpenCategory = ref(false)
const isOpenPriority = ref(false)
const isOpenSuggestionUser = ref(false)
const isOpenSuggestionId = ref(false)
const isSaveMessage = ref(false)
const isTooltip = ref([])
const isModalReferenceType = ref(false)
const isModalSign = ref(false)
const attach = ref(null)
const sign = ref('')
const receiver = ref('')
const inputFindLetter = ref('')
const categories = ref(['SECRET', 'CONFIDENTIAL', 'NORMAL'])
const priorities = ref(['INSTANT', 'IMMEDIATELY', 'NORMAL'])
const errors = ref(null)
const sugUsers = ref([])
const sugFindLetter = ref([])
const inputUsers = ref(null)
const routeItem = reactive({
  saveMsg: '',
  submit: ''
})
const isDraft = ref(false)
const limitFile = 3

const { focused } = useFocus(inputUsers)
const { url } = usePage()

const icons = [
  {
    icon: PaperClipIcon,
    name: 'Attachment'
  },
  {
    icon: DocumentCheckIcon,
    name: 'saveMessage'
  },
  {
    icon: ReferenceTypeIcon,
    name: 'referenceType'
  }
]
const form = useForm({
  usersName: [],
  users: [],
  subject: '',
  text: '',
  description: '',
  dueDate: '',
  signs: [],
  category: 'NORMAL',
  priority: 'NORMAL',
  attachments: [],
  referenceType: 'REFERENCE',
  referenceId: '',
  referenceName: '',
  startDate: '',
  endDate: '',
  isSubmit: false
})

const AlertOption = reactive({
  isAlert: false,
  dataList: [],
  status: '',
  data: ''
})

const signFilter = computed(() => {
  return sign.value
    ? form.usersName.filter(user =>
      user.toLowerCase().includes(sign.value.toLowerCase())
    )
    : form.usersName
})

onMounted(() => {
  setRoute()
  if (url.value.includes('show-draft')) {
    form.subject = props.subject
    form.text = props.text
    form.description = props.description
    form.category = props.category
    form.priority = props.priority
    form.dueDate = props.dueDate
    form.referenceType = props.referenceType || 'REFERENCE'
    form.referenceId = props.referenceId
  }
})

const setColorCategory = (item) => ({
  'bg-blue-100 text-blue-900': item === 'NORMAL',
  'bg-red-100 text-red-900': item === 'SECRET' || item === 'INSTANT',
  'bg-orange-100 text-orange-900 ': item === 'CONFIDENTIAL' || item === 'IMMEDIATELY'
})

const convertFileSize = (fileSize) => {
  const convertSize = fileSize / 1000000
  const formattedSize = convertSize.toFixed(2)
  return `(${formattedSize} مگابایت)`
}

const conditionItems = (name) => {
  if (name === 'saveMessage') return isSaveMessage.value ? 'stroke-green-500' : 'stroke-gray-800'
  if (name === 'referenceType') return form.referenceId ? 'stroke-secondPrimary' : 'stroke-gray-800'
}

function setRoute () {
  isDraft.value = url.value.includes('show-draft')
  routeItem.saveMsg = isDraft.value ? 'web.user.cartable.drafted.submit' : 'web.user.cartable.draft.action'
  routeItem.submit = isDraft.value ? 'web.user.cartable.drafted.submit' : 'web.user.cartable.submit.action'
}

function findNameSignsInUsers (signer) {
  const findInUser = form.users.findIndex(u => u === signer)
  return findInUser !== -1 ? form.usersName[findInUser] : null
}

function choiceFunction (item) {
  const functionMap = {
    Attachment: openModalAttach,
    saveMessage,
    referenceType: openModalReferenceType
  }
  functionMap[item.name]?.()
}

function openModalAttach () {
  attach.value.click()
}

function addFile (files) {
  // set 3 file
  const filesBigSize = []
  for (let i = 0; i < files.length; i++) {
    // validation size files
    if (files[i].size < 5_000_000) {
      form.attachments.push(files[i])
    } else {
      filesBigSize.push(files[i].name)
      setAlerts(true, 'error', 'حداکثر حجم هر فایل الصاقی ۵ مگابایت میتواند باشد .', filesBigSize)
    }
  }

  // validation limit number file attachments
  if (form.attachments.length > limitFile) {
    setAlerts(true, 'error', 'حداکثر ۳ فایل می‌توانید آپلود بفرمایید .')
    form.attachments = form.attachments.slice(-3)
  }
}

function UploadFiles (e) {
  e.preventDefault()
  const files = e.target.files || e.dataTransfer.files
  if (!files.length) return
  addFile(files)
}

function openModalReferenceType () {
  isModalReferenceType.value = true
}

function subReferenceType () {
  isModalReferenceType.value = false
}

function characterLimit () {
  if (form.subject.length > 250) form.subject = form.subject.slice(0, 250)
}

function setAlerts (isAlert, status, data = '', dataList = []) {
  AlertOption.status = status
  AlertOption.data = data
  AlertOption.dataList = dataList
  AlertOption.isAlert = isAlert
}

function saveMessage () {
  form.isSubmit = false
  form.post(route(routeItem.saveMsg), {
    onError: (e) => {
      errors.value = Object.values(e).flat()
      setAlerts(true, 'error', 'نامه ذخیره نشد.', errors.value)
    },
    onSuccess: () => {
      isSaveMessage.value = true
      setAlerts(true, 'success', 'پیام با موفقیت ذخیره شد.')
    }
  })
}

function subCategory (cat) {
  form.category = cat
  isOpenCategory.value = false
}

function subPriority (item) {
  form.priority = item
  isOpenPriority.value = false
}

function removeSigners (i) {
  form.signs.splice(i, 1)
}

function setUsers (id, name) {
  const existingUser = form.users.find((u) => u === id)
  if (existingUser) return
  form.users.push(id)
  form.usersName.push(name)
}

function removeUser (i) {
  form.users.splice(i, 1)
  form.usersName.splice(i, 1)
}

function removeLetterInReference () {
  form.referenceId = ''
}

function removeAttachFile (i) {
  form.attachments.splice(i, 1)
}

function resetAction () {
  form.reset()
  isSaveMessage.value = false
}

function setSuggestionUser (id, name) {
  setUsers(id, name)
  isOpenSuggestionUser.value = false
  focused.value = true
  receiver.value = ''
}

function setSuggestionId (sug) {
  form.referenceId = sug.id
  form.referenceName = sug.subject
  isOpenSuggestionId.value = false
  inputFindLetter.value = ''
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

const fetchSuggestionLetter = debounce(async () => {
  if (inputFindLetter.value?.length < 1 || !inputFindLetter.value) {
    sugFindLetter.value = []
    return
  }
  try {
    const res = await axios.post(route('web.user.api.letters'), { identifier: inputFindLetter.value })
    if (res.status === 200) {
      sugFindLetter.value = res.data
      isOpenSuggestionId.value = true
    }
  } catch (e) {
    console.error('Error fetching suggestion id:', e)
  }
}, 500)

watch(inputFindLetter, fetchSuggestionLetter)

function removeItemUsers (e) {
  if (e.key === 'Backspace') {
    if (receiver.value && receiver.value?.length > 0) return
    receiver.value = form.usersName.at(-1)
    removeUser(form.users.length - 1)
  }
}

function subCreateLetter () {
  form.isSubmit = true
  form.post(route(routeItem.submit, { letter: props.id }), {
    replace: true,
    preserveScroll: true,
    onError: (e) => {
      errors.value = Object.values(e).flat()
      setAlerts(true, 'error', 'نامه ارسال نشد.', errors.value)
    },
    onSuccess: () => {
      setAlerts(true, 'success', 'نامه ارسال شد.')
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

.v3ti {
  border: none;
  outline: none;
  box-shadow: none;

  &-content {
    align-items: center !important;

    &-tag {
      margin: 0 !important;
      background: red !important;
    }
  }

  .v3ti-new-tag {
    box-shadow: none !important;
    margin: 0 0.5rem !important;
  }
}
</style>
