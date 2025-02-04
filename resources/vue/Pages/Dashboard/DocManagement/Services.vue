<template>
  <HeaderServicePage
    class="z-20"
    :activities="activities"
    :parent-folder="file?.previousPage"
    :auth-user="authUser"
    :file="file"
    :searched-input="searchedInput"
    :download-route="downloadRoute"
    :file-type="fileType"
    @search-data="onSearchData"
    @print-file="handleEmittedPrint" />
  <IttService
    v-if="component==='ITT'"
    :file="fileContent"
    :content="file"
    :search="searchText"
    :file-type="fileType"
    :print-route="printRoute"
    :is-print="isPrint"
    :is-download="isDownload"
    @print-action="isPrint=false"
    @downloa-action="isDownload=false" />
  <SttService
    v-if="component==='STT'"
    :file="file"
    :search="searchText"
    :print-route="printRoute"
    :list-value="voiceWindows"
    :is-print="isPrint"
    :content="fileContent"
    @print-action="isPrint=false" />
  <VttService
    v-if="component==='VTT'"
    :file="file"
    :search="searchText"
    :print-route="printRoute"
    :list-value="voiceWindows"
    :is-print="isPrint"
    :content="fileContent"
    @print-action="isPrint=false" />
</template>

<script setup>
import HeaderServicePage from '../../Components/~AppHeaderServicePage.vue'
import layout from '../../../Layouts/~AppLayoutServicePage.vue'
import IttService from '../../Components/IttService.vue'
import SttService from '../../Components/SttService.vue'
import VttService from '../../Components/VttService.vue'
import { ref } from 'vue'

defineOptions({
  name: 'Services',
  layout
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  authUser: { type: Object, required: true },
  file: { type: Object, required: true },
  fileContent: { type: String, required: true },
  fileType: { type: String, required: true },
  voiceWindows: { type: Array, required: true },
  component: { type: String, required: true },
  searchedInput: { type: String, default: '' },
  downloadRoute: { type: String, default: '' },
  printRoute: { type: String, required: true },
  activities: { type: Array, required: true }
})

const searchText = ref('')
const isPrint = ref(false)
const isDownload = ref(false)

function onSearchData (data) {
  searchText.value = data
}

function handleEmittedPrint () {
  isPrint.value = true
}

// function handleEmittedDownload () {
//   isDownload.value = true
// }
</script>
