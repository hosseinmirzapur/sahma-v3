<template>
  <div class="p-5">
    <!-- advance search -->
    <div
      v-if="isOpenAdvanceSearch"
      class="p-5 rounded-xl">
      <!-- search -->
      <div
        class="w-full relative rounded-xl flex justify-between items-center md:h-20 h-16 py-5
        focus-within:text-secondPrimary text-gray-400">
        <input
          ref="inputRef"
          v-model="form.searchable_text"
          type="search"
          name="search"
          placeholder="جستجو کنید"
          class="placeholder-gray-400 bg-transparent border-x-0 border-t-0 border-b
          border-gray-300 text-gray-800 w-full py-2 pr-14 pl-5 focus:outline-none focus:bg-transparent
          focus:border-secondPrimary focus:ring-0 disabled:opacity-50"
          @keydown.enter="subSearch">
        <MagnifyingGlassIcon class="absolute top-6 right-5 w-6" />
      </div>
      <!-- errors -->
      <ul
        v-if="errors?.length > 0"
        class="list-inside text-sm text-red-600 pt-2">
        <li
          v-for="(e , i) in errors"
          :key="i"
          class="text-start"
          v-text="getErrorText(e)" />
      </ul>

      <div class="mx-auto flex flex-col">
        <div
          v-for="(item , key , i) in categorySearch"
          :key="i"
          class="w-full px-5 py-3 mb-3 bg-primary/5 border-b-2 border-gray-200 rounded-t-lg ">
          <!-- header-->
          <div
            class=" flex flex-row justify-between items-center cursor-pointer"
            @click.stop.prevent="isOpenFile[i]=!isOpenFile[i]">
            <div class=" flex items-center">
              <p
                class="transition-colors text-right text-primaryText select-none text-md text-primaryText cursor-pointer"
                v-text="item" />
            </div>

            <div class="flex">
              <!-- Badge -->
              <span
                v-if="conditionBadge(i , key)"
                class="justify-items-end items-center bg-green-100 text-green-800 text-xs cursor-pointer
                font-medium mr-2 px-2.5 py-0.5 rounded-full dark:bg-green-900 dark:text-green-300">
                <span class="w-2 h-2 mx-1 mr-1 bg-green-500 rounded-full" />
                فیلتر شده
              </span>
              <ChevronUpIcon
                v-if="isOpenFile[i]"
                class="w-6 cursor-pointer" />
              <ChevronDownIcon
                v-else
                class="w-6 cursor-pointer" />
            </div>
          </div>
          <!-- body file-->
          <div
            v-if="isOpenFile[i] && key === 'optionFile'"
            class="grid grid-cols-2 gap-5 w-full transition-all text-right text-primaryText
            font-thin text-xs lg:text-base my-6">
            <CustomInput
              v-model="form.fileName"
              input-label="نام فایل"
              input-type="text"
              input-name="fileName"
              class="mt-10 select-none"
              :is-search-input="true" />
            <div class="flex flex-col items-start relative my-10 cursor-pointer select-none">
              <label class="block text-base text-gray-900 w-48">نوع فایل</label>
              <div
                class="flex flex-row justify-start gap-x-2 items-center mt-3 rounded-lg border border-gray-300 !bg-white w-full
                h-[2.5rem] py-2 px-3 focus:outline-none focus:border-none focus:ring-0 relative"
                @click.stop="toggleDropdown('fileStatus')">
                <p>
                  <span
                    class="mx-2 px-2 text-gray-900 rounded-md"
                    v-text="dictionary(form.fileStatus)" />
                </p>
                <ChevronUpIcon
                  v-if="isOpenFileStatus"
                  class="absolute left-3 w-4 text-gray-900" />
                <ChevronDownIcon
                  v-else
                  class="absolute left-3 w-4 text-gray-900" />
              </div>
              <div
                v-if="isOpenFileStatus"
                class="absolute right-0 top-20 shadow-lg w-full px-5 py-3 bg-white z-10"
                @click.stop>
                <div
                  v-for="(file, iStatus) in fileStatus"
                  :key="iStatus"
                  class="flex items-center gap-x-3 py-2">
                  <input
                    :id="iStatus"
                    v-model="form.fileStatus"
                    :value="file"
                    type="radio"
                    class="w-6 h-6 rounded-full focus:ring-secondPrimary">
                  <label
                    :for="iStatus"
                    class="text-base font-medium text-gray-400 dark:text-gray-500 w-full text-start"
                    v-text="dictionary(file)" />
                </div>
              </div>
            </div>
            <div class="flex flex-col items-start relative cursor-pointer select-none">
              <label class="block text-base text-gray-900 w-48">فرمت اصلی</label>
              <div class="w-full relative">
                <div
                  class="flex flex-row justify-start gap-x-2 items-center mt-3 rounded-lg border border-gray-300 !bg-white w-full
                    h-[2.5rem] py-2 px-3 focus:outline-none focus:border-none focus:ring-0"
                  @click.stop="toggleDropdown('fileType')">
                  <p>
                    <span
                      class="mx-2 px-2  rounded-md text-gray-900"
                      v-text="dictionary(form.fileType)" />
                  </p>
                  <ChevronUpIcon
                    v-if="isOpenFileType"
                    class="absolute left-3 w-4 text-gray-900" />
                  <ChevronDownIcon
                    v-else
                    class="absolute left-3 w-4 text-gray-900" />
                </div>
                <!-- file type -->
                <div
                  v-if="isOpenFileType"
                  class="absolute right-0 top-14 shadow-lg w-full px-5 py-3 bg-white z-10"
                  @click.stop>
                  <!--  item 'all' -->
                  <div class="flex items-center gap-x-3 py-2">
                    <input
                      id="all"
                      v-model="form.fileType"
                      value="all"
                      type="radio"
                      class="w-6 h-6 rounded-full focus:ring-secondPrimary">
                    <label
                      for="all"
                      class="text-base font-medium text-gray-400 dark:text-gray-500 w-full text-start"
                      v-text="dictionary('all')" />
                  </div>

                  <!--  items-->
                  <div
                    v-for="(type, keys, j) in extensions"
                    :key="j"
                    class="flex items-center gap-x-3 py-2">
                    <input
                      :id="j"
                      v-model="form.fileType"
                      :value="keys"
                      type="radio"
                      class="w-6 h-6 rounded-full focus:ring-secondPrimary">
                    <label
                      :for="j"
                      class="text-base font-medium text-gray-400 dark:text-gray-500 w-full text-start"
                      v-text="dictionary(keys)" />
                  </div>
                </div>
              </div>
            </div>
            <div class="flex flex-col items-start relative cursor-pointer select-none">
              <label class="block text-base text-gray-900 w-48">فرمت پسوند</label>
              <div class="w-full relative">
                <div
                  class="flex flex-row justify-start gap-x-2 items-center mt-3 rounded-lg border border-gray-300 !bg-white w-full
                           h-[2.5rem] py-2 px-3 focus:outline-none focus:border-none focus:ring-0"
                  :class="{ 'pointer-events-none bg-gray-100' : form.fileType === 'all'}"

                  @click.stop="toggleDropdown('format')">
                  <p
                    v-for="(itemEx , iEx) in form.fileExtension"
                    :key="iEx"
                    class="mx-1 px-3 text-sm rounded-md text-gray-900 bg-green-500/20"
                    v-text="itemEx" />
                  <ChevronUpIcon
                    v-if="isOpenExtensions"
                    class="absolute left-3 w-4 text-gray-900" />
                  <ChevronDownIcon
                    v-else
                    class="absolute left-3 w-4 text-gray-900" />
                </div>
                <div
                  v-if="form.fileType === 'all' ? '' : isOpenExtensions"
                  class="absolute right-0 top-14 shadow-lg w-full px-5 py-3 bg-white z-10 "
                  @click.stop>
                  <div
                    v-for="(format, j) in extensions[form.fileType]"
                    :key="j"
                    class="flex items-center gap-x-3 py-2">
                    <input
                      :id="j"
                      v-model="form.fileExtension"
                      :value="format"
                      type="checkbox"
                      class="w-6 h-6 rounded-sm focus:ring-secondPrimary">
                    <label
                      :for="j"
                      class="text-base font-medium text-gray-400 dark:text-gray-500 w-full text-start"
                      v-text="format" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- body department-->
          <div
            v-if="isOpenFile[i] && key === 'optionDepartment'"
            class="w-full transition-all text-right text-primaryText font-thin text-xs lg:text-base mt-6">
            <div class="flex flex-col items-start relative mt-10 cursor-pointer select-none">
              <label class="block text-base text-gray-900 w-48">واحد</label>
              <div
                class="flex flex-row justify-start gap-x-2 items-center mt-3 rounded-lg border border-gray-300 !bg-white w-full
            h-[2.5rem] py-2 px-3 focus:outline-none focus:border-none focus:ring-0 relative"
                @click.stop="toggleDropdown('departments')">
                <p class="">
                  <span
                    v-for="(dep, j) in form.departments"
                    :key="j"
                    class="relative mx-1 px-3 pl-8 text-sm rounded-md text-gray-900 bg-green-500/20">
                    {{ authUser.departments.find(v => v.id === dep)?.name + '  ' }}
                    <XMarkIcon
                      class="w-8 absolute top-1 px-2 left-0 cursor-pointer"
                      @click.stop="deleteDepartments(j)" />
                  </span>
                </p>
                <ChevronUpIcon
                  v-if="isOpenDepartments"
                  class="absolute left-3 w-4 text-gray-900" />
                <ChevronDownIcon
                  v-else
                  class="absolute left-3 w-4 text-gray-900" />
              </div>
              <div
                v-if="isOpenDepartments"
                class="absolute right-0 top-20 shadow-lg w-full px-5 py-3 bg-white z-10"
                @click.stop>
                <div
                  v-for="(depart, iDep) in authUser?.departments"
                  :key="iDep"
                  class="flex items-center gap-x-3 py-2">
                  <input
                    :id="depart.id"
                    v-model="form.departments"
                    :value="depart.id"
                    type="checkbox"
                    class="w-6 h-6 rounded-sm focus:ring-secondPrimary">
                  <label
                    :for="depart.id"
                    class="text-base font-medium text-gray-400 dark:text-gray-500 w-full text-start"
                    v-text="depart.name" />
                </div>
              </div>
            </div>
          </div>

          <!-- body date-->
          <div
            v-if="isOpenFile[i] && key === 'optionDate'"
            class="w-full transition-all text-right text-primaryText font-thin text-xs lg:text-base mt-6">
            <div class="flex flex-col items-start col-span-2 relative my-10">
              <label class="block text-base text-gray-900 select-none"> تاریخ</label>
              <div class="flex items-center gap-x-2 mt-3 w-full">
                <p class="text-gray-900">
                  از
                </p>
                <DatePicker
                  v-model="form.fromDate"
                  class="border border-gray-300 rounded-lg mb-2  md:mb-0 w-full bg-white text-gray-900"
                  :class="{'border border-red-500': errors.length>0}"
                  format="jYYYY-jMM-jDD"
                  display-format="jYYYY/jMM/jDD"
                  simple
                  popover />
                <p class="text-gray-900">
                  تا
                </p>
                <DatePicker
                  v-model="form.toDate"
                  class="border border-gray-300 rounded-lg mb-2 md:mb-0 w-full bg-white text-gray-900"
                  :class="{'border border-red-500': errors.length>0}"
                  format="jYYYY-jMM-jDD"
                  display-format="jYYYY/jMM/jDD"
                  simple
                  popover />
              </div>
            </div>
          </div>

          <!-- body admin-->
          <div
            v-if="isOpenFile[i] && key === 'optionAdmin'"
            class="grid grid-cols-2 gap-5 items-end w-full transition-all text-right text-primaryText
            font-thin text-xs lg:text-base my-6">
            <div class="flex flex-col items-start relative cursor-pointer select-none">
              <label class="block text-base text-gray-900 w-48">ادمین</label>
              <div
                class="flex flex-row justify-start gap-x-2 items-center mt-3 rounded-lg border border-gray-300 !bg-white w-full
                h-[2.5rem] py-2 px-3 focus:outline-none focus:border-none focus:ring-0 relative"
                @click.stop="toggleDropdown('adminStatus')">
                <p>
                  <span
                    class="mx-2 px-2 text-gray-900 rounded-md"
                    v-text="dictionary(form.adminType)" />
                </p>
                <ChevronUpIcon
                  v-if="isOpenAdminStatus"
                  class="absolute left-3 w-4 text-gray-900" />
                <ChevronDownIcon
                  v-else
                  class="absolute left-3 w-4 text-gray-900" />
              </div>
              <div
                v-if="isOpenAdminStatus"
                class="absolute right-0 top-20 shadow-lg w-full px-5 py-3 bg-white z-10"
                @click.stop>
                <div
                  v-for="(admin, aStatus) in adminStatus"
                  :key="aStatus"
                  class="flex items-center gap-x-3 py-2">
                  <input
                    :id="aStatus"
                    v-model="form.adminType"
                    :value="admin"
                    type="radio"
                    class="w-6 h-6 rounded-full focus:ring-secondPrimary">
                  <label
                    :for="aStatus"
                    class="text-base font-medium text-gray-400 dark:text-gray-500 w-full text-start"
                    v-text="dictionary(admin)" />
                </div>
              </div>
            </div>
            <!--  item 'admin name' -->
            <input
              v-model="form.adminIdentifier"
              :disabled="form.adminType!=='identifier'"
              placeholder="لطفا نام ادمین مورد نظر را وارد کنید"
              class="w-full h-10 rounded-lg border border-gray-300 focus:ring-secondPrimary disabled:opacity-50">
          </div>
        </div>
      </div>

      <div class="flex justify-center gap-x-2">
        <button
          class="text-center bg-primary border-primary mt-10 text-sm border-4 text-white w-36 py-2 px-4 rounded-xl"
          @click="subSearch"
          v-text="`تایید`" />
        <Link
          :href="$route('web.user.dashboard.index')"
          class="flex justify-center items-center bg-white hover:text-white hover:bg-secondPrimary border border-primary hover:border-none mt-10
          text-sm text-primary w-36 py-2 px-4 rounded-xl">
          انصراف
        </Link>
      </div>
    </div>

    <!-- search result -->
    <div class="bg-green-50">
      <p v-if="files[0] === 'empty'" />
      <div
        v-else-if="files.length===0"
        class="flex justify-center flex-col items-center gap-y-8 h-full mt-12">
        <!-- icons empty-->
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="194"
          height="180"
          viewBox="0 0 194 180"
          fill="none">
          <circle
            cx="88.316"
            cy="88.6803"
            r="88.317"
            fill="#EAEBF1" />
          <circle
            cx="87.5936"
            cy="88.8329"
            r="51.5809"
            fill="#F5F8FF"
            stroke="#8AA6FF"
            stroke-width="2.46095" />
          <circle
            cx="87.5935"
            cy="88.8338"
            r="36.492"
            fill="#FFFEFF"
            stroke="#8AA6FF"
            stroke-width="2.46095" />
          <path
            d="M87.5935 61.1007C87.5935 60.5467 87.1441 60.0958 86.5905 60.1151C79.6597 60.357 73.0367 63.0991 67.957 67.8535C62.8773 72.6079 59.7034 79.0352 59.004 85.9348C58.9481 86.4859 59.3683 86.9641 59.9211 87.0007V87.0007C60.4738 87.0374 60.9496 86.6185 61.0069 86.0675C61.6718 79.6759 64.6198 73.7246 69.3278 69.3181C74.0358 64.9116 80.169 62.3634 86.5906 62.1225C87.1441 62.1017 87.5935 61.6546 87.5935 61.1007V61.1007Z"
            fill="#8AA6FF" />
          <rect
            x="127.802"
            y="144.834"
            width="17.2267"
            height="51.68"
            rx="8.61333"
            transform="rotate(-45 127.802 144.834)"
            fill="#F5F8FF"
            stroke="#8AA6FF"
            stroke-width="2.46095" />
          <rect
            x="145.984"
            y="148.982"
            width="2.39741"
            height="20.1016"
            rx="1.1987"
            transform="rotate(-45 145.984 148.982)"
            fill="white" />
          <rect
            x="126.052"
            y="132.645"
            width="2.46095"
            height="9.8438"
            transform="rotate(-45 126.052 132.645)"
            fill="#8AA6FF" />
          <rect
            x="152.163"
            y="82.6348"
            width="24.6095"
            height="2.46095"
            rx="1.23048"
            fill="#8AA6FF" />
          <rect
            x="-0.000976562"
            y="102.994"
            width="24.6095"
            height="2.46095"
            rx="1.23048"
            fill="#8AA6FF" />
          <rect
            x="181.694"
            y="82.6348"
            width="12.3048"
            height="2.46095"
            rx="1.23048"
            fill="#8AA6FF" />
          <rect
            x="158.315"
            y="91.6641"
            width="12.3048"
            height="2.46095"
            rx="1.23048"
            fill="#8AA6FF" />
          <rect
            x="6.15137"
            y="112.023"
            width="12.3048"
            height="2.46095"
            rx="1.23048"
            fill="#8AA6FF" />
        </svg>
        <p class="text-center text-primary/70">
          نتیجه ای یافت نشد
        </p>
      </div>
      <div
        v-else
        class="">
        <div
          v-if="files?.length>0 || files[0] === 'empty'"
          class="mt-10 px-5">
          {{ files?.length }} مورد یافت شد
        </div>
        <div
          class="flex flex-wrap items-center gap-10 text-center font-medium text-lg mt-10
                 bg-green-300/5 h-full p-5 rounded-xl">
          <div
            v-for="(doc, i) in files"
            :key="i"
            class="relative flex items-center gap-x-4 cursor-pointer border border-transparent
                 hover:border hover:shadow-cardUni rounded rounded-xl pl-3 select-none"
            :class="{'shadow-cardUni': isSelectItemFile === doc?.id }"
            @dblclick="openFile(doc?.slug)"
            @click.stop="selectItems(doc?.id)">
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
              class="text-lg cursor-pointer select-none"
              v-text="doc.name" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  DocumentIcon,
  MicrophoneIcon,
  VideoCameraIcon,
  ChevronDownIcon,
  MagnifyingGlassIcon,
  ChevronUpIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useForm, Link } from '@inertiajs/inertia-vue3'
import CustomInput from '../../Components/CustomInput.vue'
import DatePicker from 'vue3-persian-datetime-picker'
import layout from '../../../Layouts/~AppLayoutSearch.vue'
import { Inertia } from '@inertiajs/inertia'
import { dictionary } from '../../../globalFunction/dictionary.js'

defineOptions({
  name: 'SearchPage',
  layout
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Array, required: true },
  files: { type: Array, required: true },
  searchableText: { type: String, default: null },
  extensions: { type: Object, required: true }
})
const isOpenAdvanceSearch = ref(true)
const isOpenFileStatus = ref(false)
const isOpenAdminStatus = ref(false)
const isOpenFileType = ref(false)
const isOpenDepartments = ref(false)
const isOpenExtensions = ref(false)
const form = useForm({
  fileStatus: 'all',
  fileType: 'all',
  fileExtension: [],
  departments: [],
  toDate: '',
  fromDate: '',
  fileName: '',
  searchable_text: '',
  adminType: 'all',
  adminIdentifier: ''
})
const fileStatus = ref(['all', 'transcribed', 'not_transcribed'])
const adminStatus = ref(['all', 'owner', 'other', 'identifier'])
const errors = ref([])
const isOpenFile = ref([])
const categorySearch = {
  optionFile: 'بر اساس اطلاعات فایل‌ها',
  optionDepartment: 'بر اساس واحد‌ها',
  optionDate: 'بر اساس تاریخ',
  optionAdmin: 'بر اساس ادمین '
}
const isSelectItemFile = ref(null)

onMounted(() => {
  form.searchable_text = props.searchableText ?? ''
})

watch(() => form.fileType, () => {
  form.fileExtension = []
})

function closeDropdown () {
  isOpenFileStatus.value = false
  isOpenFileType.value = false
  isOpenDepartments.value = false
  isOpenExtensions.value = false
  isSelectItemFile.value = false
  isOpenAdminStatus.value = false
}

// Attach the event listener
window.addEventListener('click', closeDropdown)

// Cleanup when the component is unmounted
onBeforeUnmount(() => {
  window.removeEventListener('click', closeDropdown)
})

function selectItems (id) {
  isSelectItemFile.value = id
}

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

const conditionBadge = (i, key) => {
  switch (key) {
    case 'optionFile' :
      return form.fileType !== 'all' || form.fileStatus !== 'all' || form.fileExtension?.length > 0 || form.fileName
    case 'optionDepartment' :
      return form.departments?.length > 0
    case 'optionDate' :
      return form.toDate || form.fromDate
  }
}

const dropdownStates = {
  fileStatus: isOpenFileStatus,
  fileType: isOpenFileType,
  format: isOpenExtensions,
  departments: isOpenDepartments,
  adminStatus: isOpenAdminStatus
}

const toggleDropdown = (selectedDropdown) => {
  for (const dropdown in dropdownStates) {
    if (dropdown === selectedDropdown) {
      dropdownStates[dropdown].value = !dropdownStates[dropdown].value
    } else {
      dropdownStates[dropdown].value = false
    }
  }
}

const getErrorText = (error) => {
  switch (error) {
    case 'dateError':
      return 'تاریخ درست وارد نشده'
    default:
      return error
  }
}

function deleteDepartments (index) {
  form.departments.splice(index, 1)
}

function validationDate () {
  errors.value = []
  if (form.fromDate > form.toDate) {
    errors.value.push('dateError')
    return true
  }
}

function subSearch () {
  if (validationDate()) return
  if (!form.fileType) form.fileType = 'all'
  form.post(route('web.user.dashboard.search-action'), {
    replace: false,
    preserveScroll: true,
    onError: (e) => {
      errors.value = Object.values(e).flat()
    },
    onSuccess: () => {
      closeDropdown()
      isOpenFile.value = []
    }
  })
}

function openFile (slug) {
  Inertia.visit(route('web.user.dashboard.file.show', { fileId: slug }), {
    data: { searchable_text: form.searchable_text },
    method: 'get',
    replace: true,
    preserveState: true // Add preserveState option
  })
}
</script>
