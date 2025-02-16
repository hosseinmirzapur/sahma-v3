<template>
  <div class="p-5 min-h-[calc(100vh-9rem)]">
    <ToolBar
      v-model="textSearch"
      :is-refresh="isRefreshIcon"
      :is-active="form.files.length>0 || form.folders.length>0"
      :is-check-box="isCheckBoxing"
      @item-click="getItemInToolbar" />

    <Breadcrumb
      class="mt-2"
      :headers="breadcrumbs" />
    <ul
      v-if="(folders?.length>0 || files?.length>0) && (searchFolder.length>0 || searchFiles.length>0)"
      class="flex flex-wrap gap-5 mt-5">
      <!-- folders -->
      <template v-if="folders?.length>0">
        <div
          v-for="(doc, i) in searchFolder"
          :key="i"
          class="relative flex items-center cursor-pointer border border-transparent
                 hover:border hover:shadow-cardUni rounded-xl px-2 py-1"
          :class="{'!border-primary/20 ': isDropDowns[doc.id], 'shadow-cardUni': isSelectItemFolder === doc.id , '!border-primary/20' : form.folders.includes(doc?.id) }"
          @contextmenu.prevent="openDropDown(doc?.id,'folder')"
          @dblclick="subOpenFolder('open' ,'web.user.dashboard.folder.show',doc)">
          <input
            :id="'checkbox-folder' + doc?.id"
            v-model="form.folders"
            :value="doc?.id"
            type="checkbox"
            class="peer ml-3"
            :class="{'invisible' : !isCheckBoxing}"
            @change="!isCheckBoxing ? funSelectItem(doc?.id,'folder') : null">

          <label
            class="flex items-center gap-x-4 cursor-pointer"
            :for="'checkbox-folder' + doc?.id">
            <div class="flex justify-center items-center w-14 h-14 bg-primary/5 text-gray-600 rounded-xl">
              <FolderIcon class="w-8" />
            </div>
            <p
              class="text-lg cursor-pointer select-none"
              v-text="doc.name" />
          </label>

          <!-- drop Down folder -->
          <div
            v-if="isDropDowns[doc?.id]"
            class="z-10 absolute top-12 bg-white divide-y divide-gray-100 rounded-lg shadow-dropDownUni w-44">
            <ul class="py-2 text-sm text-gray-800">
              <li
                v-for="(item, j) in options"
                :key="j">
                <button
                  :id="item.slug"
                  class="block px-4 py-2 w-full hover:bg-gray-100 text-center "
                  :class="{'hidden' : item.slug!=='open' && authUser?.isUser ,
                           'disabled:cursor-not-allowed disabled:opacity-50 hidden' : checkMultiSelectItem(item)}"
                  :disabled="checkMultiSelectItem(item)"
                  @click.prevent="choiceFunctionSubmit(item.slug, item.link , doc ,'folder')">
                  {{ item?.title }}
                </button>
              </li>
            </ul>
          </div>
        </div>
      </template>

      <!-- files -->
      <template v-if="files?.length>0">
        <div
          v-for="(doc, i) in searchFiles"
          :key="i"
          class="relative flex items-center cursor-pointer border border-transparent
                 hover:border hover:shadow-cardUni rounded-xl px-2 py-1"
          :class="{'!border-primary/20 ': isDropDownsFiles[doc.id],
                   'shadow-cardUni': isSelectItemFolder === doc.id ,
                   '!border-primary/20' : form.files.includes(doc?.id) }"
          @contextmenu.prevent="openDropDown(doc?.id, 'file')"
          @dblclick="subOpenFiles('open' ,'web.user.dashboard.file.show',doc)">
          <input
            :id="'checkbox-' + doc?.id"
            v-model="form.files"
            :value="doc?.id"
            type="checkbox"
            class="peer ml-3"
            :class="{'invisible' : !isCheckBoxing}"
            @change="!isCheckBoxing ? funSelectItem(doc?.id,'file') : null">

          <label
            class="relative flex items-center gap-x-4 cursor-pointer"
            :for="'checkbox-' + doc?.id">
            <!-- status icons-->
            <ClockIcon
              v-if="doc?.status === 'STATUS_WAITING_FOR_MANUAL_PROCESS'"
              class="w-5 absolute -top-1 -right-2 shadow-lg text-blue-600 rounded-full animate-pulse" />
            <ClockIcon
              v-if="doc?.status === 'WAITING_FOR_TRANSCRIPTION' || doc?.status === 'WAITING_FOR_AUDIO_SEPARATION' || doc?.status === 'WAITING_FOR_SPLIT'"
              class="w-5 absolute -top-1 -right-2 shadow-lg text-orange-500 rounded-full" />
            <CheckCircleIcon
              v-if="doc?.status === 'TRANSCRIBED'"
              class="w-5 absolute -top-1 -right-2 shadow-lg text-green-700 rounded-full" />
            <ExclamationCircleIcon
              v-if="doc?.status === 'WAITING_FOR_RETRY'"
              class="w-5 absolute -top-1 -right-2 shadow-lg text-yellow-400 rounded-full" />
            <XCircleIcon
              v-if="doc?.status === 'REJECTED'"
              class="w-5 absolute -top-1 -right-2 shadow-lg text-red-500 rounded-full" />
            <!-- type files icons-->
            <div
              class="flex justify-center items-center w-14 h-14 rounded-xl"
              :class="setBgFiles(doc?.type)">
              <DocumentIcon
                v-if="doc?.type === 'pdf' || doc?.type === 'image'"
                class="w-8" />
              <DocumentWordIcon
                v-if="doc?.type === 'word'"
                class="w-8" />
              <MicrophoneIcon
                v-if="doc?.type === 'voice'"
                class="w-8" />
              <VideoCameraIcon
                v-if="doc?.type === 'video'"
                class="w-8" />
            </div>
            <p
              class="text-lg select-none cursor-pointer"
              v-text="doc.name" />
          </label>

          <!-- drop Down file -->
          <div
            v-if="isDropDownsFiles[doc?.id]"
            class="z-10 absolute top-12 bg-white divide-y divide-gray-100 rounded-lg shadow-dropDownUni cursor-pointer w-44">
            <ul class="py-2 text-sm text-gray-800">
              <li
                v-for="(item, j) in optionsFile"
                :key="j">
                <button
                  class="block w-full px-4 py-2 hover:bg-gray-100 text-center"
                  :class="{'hidden' : item.slug!=='open' && authUser?.isUser ,
                           'disabled:cursor-not-allowed disabled:opacity-50 hidden' : checkMultiSelectItem(item)}"
                  type="button"
                  :disabled="checkMultiSelectItem(item)"
                  @click.prevent="choiceFunctionSubmit(item.slug, item.link , doc ,'file')">
                  {{ item?.title }}
                </button>
              </li>
            </ul>
          </div>
        </div>
      </template>

      <!-- rename modal file and folder -->
      <Modal
        :is-open="isModalRename"
        :title="currFolder.length>0 ? 'تغییر نام پوشه' : 'تغییر نام فایل'"
        @close="resetAction">
        <form
          class="w-full"
          @submit.prevent="subRename">
          <div
            class="flex items-center border-b border-primary py-2"
            :class="{'border-red-600' : errors.length>0}">
            <input
              v-if="currFolder.length>0"
              v-model="form.folderName"
              class="appearance-none bg-transparent border-none focus:border-none w-full text-gray-700
                 py-1 px-2 leading-tight focus:outline-none shadow-transparent "
              :class="{'placeholder-red-400':errors.length > 0}"
              type="text"
              placeholder="تغییر نام پوشه ">

            <input
              v-if="currFile.length>0"
              v-model="baseNameFile"
              class="appearance-none bg-transparent border-none focus:border-none w-full text-gray-700
                 py-1 px-2 leading-tight focus:outline-none shadow-transparent "
              :class="{'placeholder-red-400':errors.length > 0}"
              type="text"
              placeholder="تغییر نام فایل">

            <button
              class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark
                 text-sm border-4 text-white py-1 px-2 rounded"
              type="submit"
              v-text="currFile.length>0 || currFolder.length>0 ?`ویرایش`: 'ایجاد'" />
          </div>
          <ul class="list-inside text-sm text-red-600 pt-2">
            <li
              v-for="(e , i) in errors"
              :key="i"
              class="text-start"
              v-text="e" />
          </ul>
        </form>
      </Modal>

      <!-- archive modal file and folder -->
      <Modal
        :is-open="isModalArchive"
        :title="currFolder ? 'آرشیو پوشه' : 'آرشیو فایل'"
        @close="resetAction">
        <form
          class="w-full"
          @submit.prevent="subArchive">
          <p v-if="currFolder.length>0">
            پوشه
            <span
              v-for="(folder , i) in currFolder"
              :key="i"
              class="font-bold px-2"
              v-text="folder?.name + '  '" />
            را آرشیو کنم؟
          </p>
          <p v-if="currFile.length>0">
            پوشه
            <span
              v-for="(file , i) in currFile"
              :key="i"
              class="font-bold px-2"
              v-text="file?.name + '  '" />
            را آرشیو کنم؟
          </p>
          <button
            class="w-full flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark
                 text-sm border-4 text-white py-1 px-2 rounded-xl mt-5"
            type="submit"
            v-text="`آرشیو شود`" />
          <ul
            v-if="errors?.length>0"
            class="list-inside text-sm text-red-600 pt-2">
            <li
              v-for="(e , i) in errors"
              :key="i"
              class="text-start"
              v-text="e" />
          </ul>
        </form>
      </Modal>

      <!-- delete modal file and folder -->
      <Modal
        :is-open="isModalDelete"
        :title="currFolder ? 'حذف پوشه' : 'حذف فایل'"
        @close="resetAction">
        <form
          class="w-full"
          @submit.prevent="subDelete">
          <p v-if="currFolder.length>0">
            پوشه
            <span
              v-for="(folder , i) in currFolder"
              :key="i"
              class="font-bold px-2"
              v-text="folder?.name + '  '" />
            را حذف کنم؟
          </p>
          <p v-if="currFile.length>0">
            پوشه
            <span
              v-for="(file , i) in currFile"
              :key="i"
              class="font-bold px-2"
              v-text="file?.name + '  '" />
            را حذف کنم؟
          </p>
          <button
            class="w-full flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark
                 text-sm border-4 text-white py-1 px-2 rounded-xl mt-5"
            type="submit"
            v-text="`حذف شود`" />
          <ul
            v-if="errors?.length>0"
            class="list-inside text-sm text-red-600 pt-2">
            <li
              v-for="(e , i) in errors"
              :key="i"
              class="text-start"
              v-text="e" />
          </ul>
        </form>
      </Modal>

      <!-- move modal folder and file-->
      <Modal
        :is-open="isModalMove"
        :title="`انتقال`"
        @close="resetAction">
        <span
          class="text-right text-red-600 text-sm py-1"
          v-text="errorMessage" />
        <div class="p-3 text-sm text-right z-30">
          <span> انتقال </span>
          <span
            v-for="(folder , i) in currFolder"
            :key="i"
            class="font-bold px-2 bg-green-50"
            v-text="folder?.name + '  '" />
          <span
            v-for="(file , j) in currFile"
            :key="j"
            class="font-bold px-2 bg-green-50"
            v-text="file?.name + '  '" />
          <span> به </span>
          <p class="font-bold px-2 inline bg-red-50">
            {{ formModal.destination.name }}
          </p>
        </div>
        <div class="h-70 overflow-auto bg-gray-100/50 shadow-inner rounded-xl p-5">
          <button
            class="w-full pb-2 text-sm text-right"
            :class="!formModal.destination.id?'text-secondPrimary':''"
            @click.prevent="setDesDashboard">
            داشبورد
          </button>
          <MoveFolder
            :destination="formModal.destination"
            :folders="authUser.folders"
            @data-to-parent="handleDataFromChild" />
        </div>
        <button
          class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark text-sm
                   border-4 h-12 shadow-btnUni text-white py-1 px-2 rounded mt-5 rounded-xl
                   cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          @click="subMove()"
          v-text="`انتقال`" />
      </Modal>

      <!-- copy modal folder and file-->
      <Modal
        :is-open="isModalCopy"
        :title="`کپی`"
        @close="resetAction">
        <div class="p-3 text-sm text-right z-30">
          <span> کپی </span>
          <span
            v-for="(folder , i) in currFolder"
            :key="i"
            class="font-bold px-2 bg-green-50"
            v-text="folder?.name + '  '" />
          <span
            v-for="(file , j) in currFile"
            :key="j"
            class="font-bold px-2 bg-green-50"
            v-text="file?.name + '  '" />
          <span> در </span>
          <p class="font-bold px-2 inline bg-red-50">
            {{ formModal.destination.name }}
          </p>
        </div>
        <div class="h-70 overflow-auto bg-gray-100/50 shadow-inner rounded-xl p-5">
          <button
            class="w-full pb-2 text-sm text-right"
            :class="!formModal.destination.id?'text-secondPrimary':''"
            @click.prevent="setDesDashboard">
            داشبورد
          </button>
          <MoveFolder
            :destination="formModal.destination"
            :folders="authUser.folders"
            @data-to-parent="handleDataFromChild" />
        </div>
        <button
          class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark text-sm
                   border-4 h-12 shadow-btnUni text-white py-1 px-2 rounded mt-5 rounded-xl
                   cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          @click="subCopy()"
          v-text="`کپی`" />
      </Modal>

      <!-- description modal file-->
      <Modal
        :is-open="isModalDescription"
        :title="`توضیحات`"
        @close="resetAction">
        <div class="p-3 pt-0 text-sm text-right z-30">
          <textarea
            v-if="currFile"
            v-model="form.description"
            rows="5"
            class="w-full h-full px-5 border border-gray-300 rounded-lg focus:outline-none focus:ring-0"
            @input="characterLimit" />
        </div>
        <div class="flex justify-center gap-x-6">
          <button
            class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary text-sm border
            shadow-btnUni text-white w-36 py-2 px-4 rounded rounded-xl cursor-pointer disabled:opacity-50
             disabled:cursor-not-allowed"
            @click.prevent="saveDescription"
            v-text="'ثبت'" />
          <button
            class="flex-shrink-0 border border-primary text-primary
              text-sm w-36 py-2 px-4 rounded-xl hover:border-primaryDark hover:text-primaryDark"
            @click.prevent="resetAction"
            v-text="`انصراف`" />
        </div>
      </Modal>

      <!--  modal sort-->
      <Modal
        :is-open="isModalSort"
        :title="`مرتب سازی`"
        @close="resetAction">
        <form class="w-full">
          <div class="flex flex-col items-start py-3 gap-y-4">
            <div
              v-for="(item, i) in sortsItem"
              :key="i"
              class="flex items-center">
              <input
                :id="'id'+i"
                v-model="sortedItems"
                type="radio"
                :value="item.value"
                name="default-radio"
                class="w-6 h-6 text-blue-600 bg-gray-100 border-gray-300 cursor-pointer"
                @change="isModalSort=false">
              <label
                :for="'id'+i"
                class="mr-2 text-base font-medium text-primaryText cursor-pointer"
                v-text="item.label" />
            </div>
          </div>
        </form>
      </Modal>

      <Modal
        :is-open="isModalDepChange"
        :title="`ویرایش دپارتمان ها`"
        @close="resetAction">
        <div class="flex flex-col items-start relative">
          <label class="block text-base text-gray-900">واحد</label>
          <div
            id="dropdown"
            class="flex flex-row justify-start gap-x-2 items-center mt-3 rounded-lg border border-gray-300
                      w-full h-[2.5rem] py-2 px-3 focus:outline-none focus:border-none focus:ring-0 relative"
            @click.stop.prevent="isOpenDepartments=!isOpenDepartments">
            <p
              v-for="(item, j) in form.departments"
              :key="j"
              class="bg-green-100/50 mx-2 px-2  rounded-md">
              <span>{{ departments.find(v => v.id === item.id)?.name + '  ' }}</span>
            </p>
            <ChevronDownIcon class="absolute left-3 w-4" />
          </div>
          <div
            v-if="isOpenDepartments"
            class="absolute right-0 top-20 shadow-lg w-full px-5 py-3 bg-white"
            @click.stop>
            <div
              v-for="(option, i) in departments"
              :key="i"
              class="flex items-center gap-x-3 py-2">
              <input
                :id="option.id"
                v-model="form.departments"
                :value="option"
                type="checkbox"
                :checked="form.departments.includes(option.id)"
                class="w-6 h-6 rounded-sm focus:ring-secondPrimary">
              <label
                :for="option.id"
                class="text-base font-medium text-gray-400 dark:text-gray-500 w-full text-start"
                v-text="option.name" />
            </div>
          </div>
        </div>
        <div class="flex justify-center">
          <button
            class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark shadow-btnUni
                      text-sm border-4 text-white w-full py-2 px-4 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed mt-3"
            @click.prevent="saveDepartments">
            تایید
          </button>
        </div>
      </Modal>
    </ul>
    <!-- empty page-->
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
        <p
          class="text-center text-primary/70"
          v-text="folders?.length===0 || files?.length===0 ? `در حال حاضر سندی موجود نیست` : `موردی یافت نشد`" />
      </div>
    </div>
  </div>
  <Alert
    class="z-20"
    :title="AlertOption.data"
    :is-open="AlertOption.isAlert"
    :contents-list="AlertOption.dataList"
    :status="AlertOption.status"
    @close="AlertOption.isAlert=false" />
</template>

<script setup>
import layout from '../../../Layouts/~AppLayout.vue'
import Breadcrumb from '../../Components/Breadcrumb.vue'
import Modal from '../../Components/Modal.vue'
import MoveFolder from '../../Components/MoveFolder.vue'
import { onBeforeUnmount, ref, computed, onMounted, reactive } from 'vue'
import {
  ChevronDownIcon,
  DocumentIcon,
  FolderIcon,
  MicrophoneIcon,
  VideoCameraIcon
} from '@heroicons/vue/24/outline'
import { ClockIcon, CheckCircleIcon, ExclamationCircleIcon, XCircleIcon } from '@heroicons/vue/24/solid'
import { useForm, usePage } from '@inertiajs/inertia-vue3'
import { Inertia } from '@inertiajs/inertia'
import ToolBar from '../../Components/ToolBar.vue'
import Alert from '../../Components/Alert.vue'
import DocumentWordIcon from '../../Components/icon/DocumentWordIcon.vue'

// eslint-disable-next-line no-undef
defineOptions({
  name: 'Index',
  layout
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  folders: { type: Object, required: true },
  files: { type: Object, required: true },
  authUser: { type: Object, required: true },
  breadcrumbs: { type: Array, default: () => [] },
  zipFileInfo: { type: Object, required: true },
  departments: { type: Array, required: true }
})

const options = ref([
  {
    title: 'ورود',
    slug: 'open',
    link: 'web.user.dashboard.folder.show'
  },
  {
    title: 'تغییر نام',
    slug: 'rename',
    link: 'web.user.dashboard.folder.rename'
  },
  {
    title: 'انتقال',
    slug: 'move',
    link: 'web.user.dashboard.folder.move-root'
  },
  {
    title: 'کپی',
    slug: 'copy',
    link: 'web.user.dashboard.folder.copy-folder'
  },
  {
    title: 'دانلود',
    slug: 'download',
    link: 'web.user.dashboard.file.download'
  },
  {
    title: 'آرشیو',
    slug: 'archive',
    link: '#'
  },
  {
    title: 'حذف',
    slug: 'delete',
    link: '#'
  }
])
const optionsFile = ref([
  {
    title: 'باز کردن',
    slug: 'open',
    link: 'web.user.dashboard.file.show'
  },
  {
    title: 'تغییر نام',
    slug: 'rename',
    link: 'web.user.dashboard.file.rename'
  },
  {
    title: 'ویرایش واحد سازمانی',
    slug: 'depChange',
    link: '#'
  },
  {
    title: 'انتقال',
    slug: 'move',
    link: 'web.user.dashboard.file.move-root'
  },
  {
    title: 'کپی',
    slug: 'copy',
    link: 'web.user.dashboard.file.copy-file'
  },
  {
    title: 'دانلود',
    slug: 'download',
    link: 'web.user.dashboard.file.download'
  },
  {
    title: 'آرشیو',
    slug: 'archive',
    link: '#'
  },
  {
    title: 'حذف',
    slug: 'delete',
    link: '#'
  },
  {
    title: 'توضیحات',
    slug: 'description',
    link: '#'
  }
])
const sortsItem = ref([
  { label: 'بر اساس تاریخ', value: 'date' },
  { label: 'بر اساس حروف الفبا', value: 'string' }
])

const isSelectItemFolder = ref(null)
const isDropDowns = ref([])
const isDropDownsFiles = ref([])
const isCheckBoxing = ref(false)
const isModalMove = ref(false)
const isModalCopy = ref(false)
const isModalRename = ref(false)
const isModalArchive = ref(false)
const isModalDelete = ref(false)
const isModalDescription = ref(false)
const isModalSort = ref(false)
const isModalDepChange = ref(false)
const isRefreshIcon = ref(false)
const isOpenDepartments = ref(false)

const form = useForm({
  folderName: '',
  folders: [],
  files: [],
  destinationFolder: null,
  description: '',
  departments: []
})
const formModal = useForm({
  destination: {
    name: 'داشبورد',
    id: ''
  }
})
const errors = ref([])
const currFolder = ref([])
const currFile = ref([])
const baseNameFile = ref('')
const extensionFile = ref('')
const textSearch = ref(null)
const sortedItems = ref('date')
const { url } = usePage()
const AlertOption = reactive({
  isAlert: false,
  dataList: [],
  status: '',
  data: ''
})
const errorMessage = ref('')

onMounted(() => {
  // Attach the event listener
  window.addEventListener('click', closeDropdown)

  // setShort keys
  window.addEventListener('keydown', (event) => {
    funShortKeys(event)
  })
})

// Cleanup when the component is unmounted
onBeforeUnmount(() => {
  window.removeEventListener('click', closeDropdown)
})

// general functions
function checkMultiSelectItem (item) {
  const mergeForm = [...form.files, ...form.folders]
  return mergeForm.length > 1 && (item?.slug === 'open' || item?.slug === 'rename' || item?.slug === 'description')
}

function funShortKeys (event) {
  if (event.shiftKey && event.key === 'A') checkedAllItems()
  if (event.shiftKey && event.key === 'C') openModalsCopy()
  if (event.shiftKey && event.key === 'M') openModalsMove()
  if (event.shiftKey && event.key === 'R') openModalArchive()
  if (event.key === 'Control') subCheckBox()
  if (event.key === 'Delete') openModalDelete()
}

function checkedAllItems () {
  if (isCheckBoxing.value) {
    // get id all folders and files
    const foldersId = props.folders.map(v => v.id)
    const filesId = props.files.map(v => v.id)

    // check all select folder and files
    const areEqFolder = form.folders.length === foldersId.length && form.folders.every((value, index) => value === foldersId[index])
    const areEqFiles = form.folders.length === foldersId.length && form.folders.every((value, index) => value === foldersId[index])
    if (areEqFolder && areEqFiles) {
      form.reset()
    } else {
      // reset form
      form.reset()

      // add all items in form (files and folders)
      form.folders = [...foldersId]
      form.files = [...filesId]
    }
  }
}

function setBgFiles (type) {
  switch (type) {
    case 'word':
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

const handleDataFromChild = (data) => {
  formModal.destination.name = data.name
  formModal.destination.id = data.id
}

function handlerCloseDropDown () {
  isDropDowns.value = []
  isDropDownsFiles.value = []
}

function setDesDashboard () {
  formModal.destination.name = 'داشبورد'
  formModal.destination.id = ''
}

function resetAction () {
  isModalCopy.value = false
  isModalMove.value = false
  isModalRename.value = false
  isModalArchive.value = false
  isModalDelete.value = false
  isModalDescription.value = false
  isModalSort.value = false
  isModalDepChange.value = false
  currFolder.value = []
  currFile.value = []
  errorMessage.value = ''
  form.reset()
  formModal.reset()
}

function subCheckBox () {
  form.reset()
  closeDropdown()
  isCheckBoxing.value = !isCheckBoxing.value
}

const itemFunctions = {
  checkBox: subCheckBox,
  copy: openModalsCopy,
  move: openModalsMove,
  download: subDownload,
  archive: openModalArchive,
  delete: openModalDelete,
  refresh: subRefresh,
  sort: openModalSort,
  filter: openSearchAdvance
}

function getItemInToolbar (item) {
  const itemFunction = itemFunctions[item]
  if (itemFunction) itemFunction()
}

function choiceFunctionSubmit (slug, link, doc, type) {
  const actions = {
    folder: {
      open: () => subOpenFolder(slug, link, doc),
      move: () => openModalsMove(type, doc),
      copy: () => openModalsCopy(type, doc),
      rename: () => openModalRename(slug, doc, type),
      download: () => subDownload(),
      archive: () => openModalArchive(),
      delete: () => openModalDelete()
    },
    file: {
      open: () => subOpenFiles(slug, link, doc),
      move: () => openModalsMove(type, doc),
      copy: () => openModalsCopy(type, doc),
      rename: () => openModalRename(slug, doc, type),
      depChange: () => openDepChange(type, doc),
      download: () => subDownload(),
      archive: () => openModalArchive(),
      delete: () => openModalDelete(),
      description: () => openModalDescription(slug, doc)
    }
  }

  if (type in actions && slug in actions[type]) {
    actions[type][slug]()
  }
}

const openDepChange = () => {
  setCurrItems()
  isModalDepChange.value = true
  if (currFile.value[0]?.departments && currFile.value[0]?.departments.length > 0) {
    // eslint-disable-next-line no-unsafe-optional-chaining
    form.departments.push(...(currFile.value[0]?.departments))
  }
  console.log(form.departments)
}

function openModalSort () {
  isModalSort.value = true
}

function openModalDescription (slug, doc) {
  setCurrItems()
  isModalDescription.value = true
  form.description = doc?.description
}

function setCurrItems () {
  props.folders.forEach(folder => {
    if (form.folders.includes(folder?.id)) return currFolder.value.push(folder)
  })

  props.files.forEach(file => {
    if (form.files.includes(file?.id)) return currFile.value.push(file)
  })
}

function openModalArchive () {
  if (form.folders.length > 0 || form.files.length > 0) {
    setCurrItems()
    isModalArchive.value = true // open modal
  }
}

function openModalDelete () {
  if (form.folders.length > 0 || form.files.length > 0) {
    setCurrItems()
    isModalDelete.value = true // open modal
  }
}

function openModalRename () {
  // just 1 items for renameAction
  setCurrItems()
  if (currFolder.value.length > 0) form.folderName = currFolder.value[0]?.name
  if (currFile.value.length > 0) splitFileNameForRename(currFile.value[0].name)
  isModalRename.value = true
}

function splitFileNameForRename (fileName) {
  if (!fileName) return
  const parts = fileName.split('.')
  extensionFile.value = parts.pop()
  baseNameFile.value = parts.join('.')
}

function mergeFileNameForRename () {
  form.fileName = `${baseNameFile.value}.${extensionFile.value}`
}

const searchFolder = computed(() => {
  return textSearch.value ? sortFoldersItem.value.filter((item) => item.name.includes(textSearch.value)) : sortFoldersItem.value
})

const searchFiles = computed(() => {
  return textSearch.value ? sortFilesItem.value.filter((item) => item.name.includes(textSearch.value)) : sortFilesItem.value
})

const sortFoldersItem = computed(() => {
  if (sortedItems.value === 'string') {
    return props.folders.toSorted((a, b) => {
      return /[آ-ی]/.test(a.name) && /[آ-ی]/.test(b.name)
        ? a.name.localeCompare(b.name, 'en-US')
        : a.name.localeCompare(b.name, 'fa-IR')
    }
    )
  }
  return props.folders
})

const sortFilesItem = computed(() => {
  if (sortedItems.value === 'string') {
    return props.files.toSorted((a, b) => {
      return /[آ-ی]/.test(a.name) && /[آ-ی]/.test(b.name)
        ? a.name.localeCompare(b.name, 'en-US')
        : a.name.localeCompare(b.name, 'fa-IR')
    }
    )
  }
  return props.files
})

function subRefresh () {
  const lastSlug = url.value.split('/').pop()
  const routeName = lastSlug === 'dashboard' ? 'web.user.dashboard.index' : 'web.user.dashboard.folder.show'
  const routeParams = lastSlug === 'dashboard' ? {} : { folderId: lastSlug }

  Inertia.visit(route(routeName, routeParams), {
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

function subArchive () {
  form.post(route('web.user.dashboard.archive-action'), {
    replace: true,
    preserveScroll: true,
    onSuccess: () => {
      resetAction()
    }
  })
}

function subDelete () {
  form.post(route('web.user.dashboard.trash-action'), {
    replace: true,
    preserveScroll: true,
    onSuccess: () => {
      resetAction()
    }
  })
}

function subRename () {
  mergeFileNameForRename()
  const routeItem = currFolder.value.length > 0 ? 'web.user.dashboard.folder.rename' : 'web.user.dashboard.file.rename'
  const type = currFolder.value.length > 0 ? 'folderId' : 'fileId'
  Inertia.visit(route(routeItem, { [type]: currFolder.value[0]?.slug ?? currFile.value[0]?.slug }), {
    method: 'post',
    data: form,
    replace: true,
    preserveState: true, // Add preserveState option
    onSuccess: () => {
      resetAction()
    }
  })
}

function subOpenFolder (slug, routeItem, doc) {
  Inertia.visit(route(routeItem, { folderId: doc?.slug }), {
    method: 'get',
    preserveState: false, // Add preserveState option
    onSuccess: () => {
      resetAction()
    }
  })
}

function openModalsMove () {
  if (form.folders.length > 0 || form.files.length > 0) {
    handlerCloseDropDown()
    setCurrItems()
    isModalMove.value = true
  }
}

function openModalsCopy () {
  if (form.folders.length > 0 || form.files.length > 0) {
    handlerCloseDropDown()
    setCurrItems()
    isModalCopy.value = true
  }
}

function subMove () {
  form.destinationFolder = formModal.destination?.id
  if (form.destinationFolder === form.folders[0]) {
    errorMessage.value = 'نمی‌توانید یک پوشه را درون خودش منتقل کنید.'
  } else {
    form.post(route('web.user.dashboard.move'), {
      replace: false,
      preserveScroll: false,
      onSuccess: () => {
        resetAction()
      }
    })
  }
}

function subCopy () {
  form.destinationFolder = formModal.destination?.id
  form.post(route('web.user.dashboard.copy'), {
    replace: false,
    preserveScroll: false,
    onSuccess: () => {
      resetAction()
    }
  })
}

function subDownload () {
  form.post(route('web.user.dashboard.create-zip'), {
    replace: true,
    preserveScroll: false,
    onSuccess: () => {
      if (!props.zipFileInfo) return
      DownloadFileZip()
    }
  })
}

function DownloadFileZip () {
  const link = document.createElement('a')
  link.href = props.zipFileInfo?.downloadUrl
  link.target = '_blank' // Open the link in a new tab/window
  link.download = props.zipFileInfo?.zipFileName // Specify the desired download filename

  link.click()
}

function openSearchAdvance () {
  Inertia.visit(route('web.user.dashboard.search-form'), {
    method: 'get'
  })
}

// folder functions
function funSelectItem (id, type) {
  if (isCheckBoxing.value) {
    setForm(id, type)
  } else {
    form.reset()
    setForm(id, type)
  }
}

function setForm (id, type) {
  const formKey = type === 'folder' ? 'folders' : 'files'
  const targetArray = form[formKey]

  const index = targetArray.indexOf(id)
  if (index !== -1) {
    targetArray.splice(index, 1)
  } else {
    targetArray.push(id)
  }
}

function setFormRightClick (id, type) {
  const formKey = type === 'folder' ? 'folders' : 'files'

  if (!form[formKey].includes(id)) {
    form.reset()
    form[formKey].push(id)
  }
}

function openDropDown (id, type) {
  closeDropdown()
  // check checkbox
  if (!isCheckBoxing.value) form.reset()

  setFormRightClick(id, type)
  if (type === 'folder') isDropDowns.value[id] = !isDropDowns.value[id]
  else isDropDownsFiles.value[id] = !isDropDownsFiles.value[id]
}

function setAlerts (isAlert, status, data = '', dataList = []) {
  AlertOption.status = status
  AlertOption.data = data
  AlertOption.dataList = dataList
  AlertOption.isAlert = isAlert
}

function characterLimit () {
  if (form.description.length > 200) form.description = form.description.slice(0, 200)
}

function saveDescription () {
  const routeItem = 'web.user.dashboard.file.add-description'
  form.post(route(routeItem, { fileId: currFile?.value[0]?.slug }), {
    replace: true,
    preserveState: true, // Add preserveState option
    onSuccess: () => {
      setAlerts(true, 'success', 'توضیحات مورد نظر اضافه شد')
      resetAction()
    },
    onError: (e) => {
      errors.value = Object.values(e).flat()
    }
  })
}

const saveDepartments = () => {
  const routeName = 'web.user.dashboard.file.modify-departments'
  form.departments = form.departments.map(dep => dep.id)
  form.post(route(routeName, { fileId: currFile.value[0]?.id }), {
    onSuccess: () => {
      setAlerts(true, 'success', 'واحد های سازمانی با موفقیت به روزرسانی شدند')
      resetAction()
    },
    onError: (e) => {
      errors.value = Object.values(e).flat()
      setAlerts(true, 'error', Object.values(e).flat())
    }
  })
}

function closeDropdown () {
  isDropDownsFiles.value.fill(false)
  isDropDowns.value.fill(false)
}

function subOpenFiles (slug, routeItem, doc) {
  if (doc?.status === 'STATUS_WAITING_FOR_MANUAL_PROCESS') {
    setAlerts(true, 'info', 'فایل در حال پردازش است، لطفا منتظر بمانید...')
    return
  }
  if (slug === 'open') {
    Inertia.visit(route(routeItem, { fileId: doc?.slug }), {
      method: 'get',
      preserveState: true // Add preserveState option
    })
  }
}

</script>
