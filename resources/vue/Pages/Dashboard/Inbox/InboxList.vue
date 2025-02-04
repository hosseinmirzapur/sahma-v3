<template>
  <div
    v-if="letters?.data?.length > 0"
    class="relative p-5 min-h-[calc(100vh-9rem)]">
    <!-- toolbar -->
    <ToolBar
      v-model.lazy="textSearch"
      :is-check-box="isCheckBoxing"
      :is-refresh="isRefreshIcon"
      :is-active="form.letters.length>0"
      :is-letter="false"
      @item-click="getItemInToolbar" />

    <!-- letter list -->
    <div
      v-for="(letter, i) in letters.data"
      :key="i"
      class="flex items-center my-5 gap-x-2">
      <input
        :id="'checkbox-' + letter?.id"
        v-model="form.letters"
        type="checkbox"
        :value="letter.id"
        :class="{'invisible' : !isCheckBoxing}"
        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
        @change="!isCheckBoxing ? funSelectItem(letter?.id) : null">
      <label
        :for="'checkbox-' + letter?.id"
        class="grid grid-cols-4 items-start rounded-l-xl hover:shadow-cardLetter border-r-4 p-3 cursor-pointer select-none
        [&>*>*>*]:cursor-pointer w-full"
        :class="setSelectLetterItem(letter)"
        @dblclick.prevent="showLetter(letter.id)">
        <div class="flex flex-col gap-y-2">
          <div class="flex items-center gap-x-2 w-max text-sm font-normal text-primaryText/80">
            <span v-text="letter.id" />
            <div
              class="text-xs rounded-full font-bold px-2 py-0.5"
              :class="setColorPriority(letter.priority)"
              v-text="dictionary(letter.priority)" />
          </div>
          <div class="flex items-center gap-x-2 w-max">
            <p
              class="text-sm font-bold text-primaryText "
              v-text="letter.sender" />
            <button
              v-if="letter?.letterReplies?.length> 0"
              class="relative flex items-center gap-x-2 text-xs rounded-full font-bold px-2 py-0.5 text-gray-900 bg-gray-100"
              @mouseover="isTooltipReply[i]=true"
              @mouseout="isTooltipReply[i]=false">
              <ArrowUturnLeftIcon class="w-3" />
              <span v-text="letter?.letterReplies?.length" />
              <transition name="tooltip">
                <div
                  v-if="isTooltipReply[i]"
                  class="absolute flex w-max bottom-5 right-0 z-10 p-2 text-xs font-medium text-primary transition-opacity
                    bg-white rounded-md tooltip">
                  <div
                    v-for="(reply , iReply) in letter?.letterReplies"
                    :key="iReply"
                    class="after:content-['_,_'] last:after:content-none"
                    v-text="reply?.userName" />
                </div>
              </transition>
            </button>
          </div>
        </div>
        <div class="flex flex-col gap-y-2 w-full px-5 col-span-2 relative">
          <div
            v-if="letter?.category==='SECRET' || letter?.category==='CONFIDENTIAL'"
            class="absolute top-2.5 rotate-[15deg] border-2 w-24 text-center font-bold py-1 rounded-lg"
            :class="setColorPriority(letter?.category)"
            v-text="dictionary(letter?.category)" />

          <div
            class="flex flex-col gap-y-2"
            :class="{'blur-sm' : letter?.category==='SECRET' || letter?.category==='CONFIDENTIAL'}">
            <div
              class="text-sm font-bold text-primaryText truncate w-full"
              v-text="letter.description" />
            <div
              class="text-xs text-primaryText/80 truncate w-full"
              v-text="letter.subject" />
          </div>

        </div>
        <div class="flex flex-col items-end gap-y-2">
          <div class="flex flex-row-reverse items-end gap-x-2">
            <p
              class="text-sm font-normal text-primaryText dir-ltr"
              v-text="letter.submittedAt" />
            <div class="flex items-center gap-x-2">
              <ClockIcon
                v-if="letter.dueDate"
                class="w-5 text-orange-700" />
              <p
                class="text-sm font-normal text-orange-700 dir-ltr"
                v-text="letter.dueDate" />
            </div>
          </div>
          <div class="flex justify-end items-end gap-x-3">
            <div
              class="bg-gray-300 text-primaryText text-sm rounded-full px-2 py-0.5 cursor-default w-max"
              v-text="dictionary(letter.status)" />
            <template
              v-for="(icon, indexIcon) in icons"
              :key="indexIcon">
              <button
                class="relative"
                :class="conditionItems(icon.name, letter)"
                @mouseover="showTooltip(indexIcon,i)"
                @mouseout="hideTooltip(indexIcon,i)">
                <component
                  :is="icon.icon"
                  class="w-4 stroke-primaryText pointer-events-none relative" />
                <transition name="tooltip">
                  <div
                    v-if="isTooltip[i] && isTooltip[i][indexIcon]"
                    class="absolute w-20 bottom-8 left-0 z-10 p-2 text-xs font-medium text-primary transition-opacity
                    bg-white rounded-md tooltip"
                    v-text="dictionary(icon.name)" />
                </transition>
              </button>
            </template>
          </div>
        </div>
      </label>
      <!-- pagination -->
    </div>

    <Pagination
      :pagination="letters?.links"
      class="absolute inset-x-0 bottom-10" />
  </div>
  <div
    v-else
    class="h-[calc(100vh-10rem)] flex justify-center item center">
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
        در حال حاضر <span v-text="setNamePageInEmptyListLetter($page.url)" /> وجود ندارد
      </p>
    </div>
  </div>
  <!-- delete modal -->
  <Modal
    :is-open="isOpenModalDelete"
    :is-delete="true"
    @close="closeModal">
    <div
      v-if="currLetter.length>0"
      class="text-right font-medium text-lg">
      آیا از پاک کردن موارد زیر مطمئن هستید؟
      <p
        v-for="(letter , i) in currLetter"
        :key="i"
        class="text-sm font-bold text-primaryText/80 my-5">
        {{ letter.id }} {{ letter.sender }}
      </p>
    </div>
    <div class="flex justify-center gap-x-5">
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
    <div
      v-if="currLetter.length>0"
      class="text-right font-medium text-lg">
      آیا از آرشیو موارد زیر مطمئن هستید؟
      <p
        v-for="(letter , i) in currLetter"
        :key="i"
        class="text-sm font-bold text-primaryText/80 my-5">
        {{ letter.id }} {{ letter.sender }}
      </p>
    </div>
    <div class="flex justify-center gap-x-5">
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
</template>

<script setup>
import layout from '../../../Layouts/~AppLayout.vue'
import ToolBar from '../../Components/ToolBar.vue'
import { onMounted, ref } from 'vue'
import Pagination from '../../Components/Pagination.vue'
import { ClockIcon, PaperClipIcon, ArrowUturnLeftIcon } from '@heroicons/vue/24/outline'
import { useForm, usePage } from '@inertiajs/inertia-vue3'
import { Inertia } from '@inertiajs/inertia'
import { dictionary } from '../../../globalFunction/dictionary.js'
import { onKeyStroke } from '@vueuse/core'
import ReferenceTypeIcon from '../../Components/icon/ReferenceTypeIcon.vue'
import Modal from '../../Components/Modal.vue'
import SignIcon from '../../Components/icon/SignIcon.vue'

defineOptions({
  name: 'InboxList',
  layout
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  letters: { type: Object, required: true }
})

onMounted(() => {
  window.addEventListener('popstate', handlePopState)
  setRoute()
  for (let i = 0; i < props.letters.data.length; i++) {
    isTooltip.value[i] = []
    for (let j = 0; j < icons.length; j++) {
      isTooltip.value[i][j] = false
    }
  }
})

onKeyStroke('Control', (e) => {
  e.preventDefault()
  subCheckBox()
})

const { url } = usePage()

const textSearch = ref(null)
const isCheckBoxing = ref(false)
const isRefreshIcon = ref(false)
const isOpenModalDelete = ref(false)
const isOpenModalArchive = ref(false)
const routeItem = ref('')
const isTooltipReply = ref([])
const icons = [
  {
    icon: ReferenceTypeIcon,
    name: 'ReferenceType'
  },
  {
    icon: PaperClipIcon,
    name: 'Attachment'
  },
  {
    icon: SignIcon,
    name: 'signMe'
  }
]
const isTooltip = ref(Array(props.letters.data.length).fill(null).map(() => Array(icons.length).fill(false)))

const errors = ref([])
const form = useForm({
  letters: [],
  isDraft: false
})

function handlePopState (event) {
  if (event.state) {
    const historyPagination = event.state?.props?.letters?.links.map(value => value)

    if (historyPagination) {
      subRefresh(event.state?.url)
    }
  }
}

const conditionItems = (name, letter) => {
  if (name === 'ReferenceType') return letter.referenceType ? 'block' : 'hidden'
  if (name === 'Attachment') return letter.attachment ? 'block' : 'hidden'
  if (name === 'signMe') return letter.signUsers ? 'block' : 'hidden'
}

const setSelectLetterItem = (letter) => {
  return [
    letter?.read_status === false ? 'bg-secondPrimary/10 ' : 'bg-gray-300/5',
    form.letters.includes(letter?.id) ? 'border-r-4 border-secondPrimary' : ''
  ]
}
const setColorPriority = (priority) => ({
  'text-red-900 bg-red-100': priority === 'INSTANT',
  'text-orange-900 bg-orange-100': priority === 'IMMEDIATELY',
  'bg-blue-100 text-blue-900': priority === 'NORMAL',
  'border-red-600 text-red-600 bg-red-500/5 ': priority === 'SECRET',
  'border-orange-500 text-orange-500 bg-orange-500/5': priority === 'CONFIDENTIAL'
})
const itemFunctions = {
  checkBox: subCheckBox,
  refresh: subRefresh,
  delete: openModalDelete,
  archive: openModalArchive
  // filter: openSearchAdvance
}

// functions
const setNamePageInEmptyListLetter = (url) => {
  switch (true) {
    case url.includes('inbox-list'):
      return 'نامه دریافتی'
    case url.includes('submit-list'):
      return 'نامه ارسالی'
    case url.includes('draft-list'):
      return 'نامه پیش‌نویسی'
    case url.includes('archived-list'):
      return 'نامه ارشیو شده‌ای'
    case url.includes('deleted-list'):
      return 'نامه حذف شده‌ای'
    default:
      return null
  }
}

function showTooltip (iTooltip, iLetter) {
  if (iLetter >= 0 && iLetter < props.letters.data.length && iTooltip >= 0 && iTooltip < icons.length) {
    isTooltip.value[iLetter][iTooltip] = true
  }
}

function hideTooltip (iTooltip, iLetter) {
  if (iTooltip >= 0 && iTooltip < icons.length) {
    isTooltip.value[iLetter][iTooltip] = false
  }
}

function funSelectItem (id) {
  if (isCheckBoxing.value) {
    setForm(id)
  } else {
    form.reset()
    setForm(id)
  }
}

function setRoute () {
  form.isDraft = url.value.includes('draft-list')
  routeItem.value = form.isDraft ? 'web.user.cartable.drafted.show' : 'web.user.cartable.letter.show'
}

function setForm (id) {
  const targetArray = form.letters
  const index = targetArray.indexOf(id)
  if (index !== -1) {
    targetArray.splice(index, 1)
  } else {
    targetArray.push(id)
  }
}

function subCheckBox () {
  form.reset()
  isCheckBoxing.value = !isCheckBoxing.value
}

function subRefresh (url) {
  let choiceRoute = ''

  const routes = [
    { keyword: 'inbox-list', route: 'web.user.cartable.inbox.list' },
    { keyword: 'submit-list', route: 'web.user.cartable.submitted.list' },
    { keyword: 'draft-list', route: 'web.user.cartable.drafted.list' },
    { keyword: 'archived-list', route: 'web.user.cartable.archived.list' },
    { keyword: 'deleted-list', route: 'web.user.cartable.deleted.list' }
  ]

  const foundRoute = routes.find(routeData => url.includes(routeData.keyword))

  if (foundRoute) {
    choiceRoute = foundRoute.route
  }
  Inertia.visit(route(choiceRoute), {
    method: 'get',
    replace: true,
    preserveState: true,
    onSuccess: () => {
      isRefreshIcon.value = true
      resetAction()
      setTimeout(() => {
        isRefreshIcon.value = false
      }, 500)
    }
  })
}

const currLetter = ref([])

function setCurrItems () {
  props.letters.data.forEach(letter => {
    if (form.letters.includes(letter?.id)) return currLetter.value.push(letter)
  })
}

function openModalDelete () {
  if (form.letters.length > 0) {
    setCurrItems()
    isOpenModalDelete.value = true
  }
}

function openModalArchive () {
  if (form.letters.length > 0) {
    setCurrItems()
    isOpenModalArchive.value = true
  }
}

function closeModal () {
  isOpenModalDelete.value = false
  isOpenModalArchive.value = false
  currLetter.value = []
}

function deleteLetters () {
  form.post(route('web.user.cartable.temp-delete.action'), {
    replace: true,
    preserveScroll: true,
    onSuccess: () => {
      resetAction()
      isOpenModalDelete.value = false
    }
  })
}

function archiveLetters () {
  form.post(route('web.user.cartable.archive.action'), {
    replace: true,
    preserveScroll: true,
    onSuccess: () => {
      resetAction()
      isOpenModalArchive.value = false
    }
  })
}

function getItemInToolbar (item) {
  const itemFunction = itemFunctions[item]
  if (itemFunction) itemFunction()
}

function resetAction () {
  form.reset()
}

function showLetter (id) {
  // window.history.pushState({ letters: props.letters?.links }, route(routeItem.value, { letter: id }))
  form.get(route(routeItem.value, { letter: id }), {
    replace: false,
    preserveScroll: true,
    // preserveState: true,
    onError: (e) => {
      errors.value = Object.values(e).flat()
    },
    onSuccess: () => {
      resetAction()
    }
  })
}

</script>

<style scoped>
.dir-ltr {
  direction: ltr;
}
</style>
