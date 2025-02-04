<template>
  <div class=" m-2 bg-white shadow-cardUni rounded-lg flex justify-center item center">
    <div class="w-1/2 p-5 mt-20">
      <Doughnut
        :chart-data="data"
        :chart-options="options" />
    </div>
  </div>
</template>

<script setup>
import layout from '../../../Layouts/~AppLayout.vue'

import {
  ArcElement,
  BarElement,
  CategoryScale,
  Chart,
  Legend,
  LinearScale,
  Title,
  Tooltip,
  PointElement,
  LineElement
} from 'chart.js'
import { Doughnut } from 'vue-chartjs'
import { reactive, onMounted } from 'vue'

Chart.defaults.font.family = 'IRANSans'
Chart.register(ArcElement, Title, Tooltip, Legend, CategoryScale, LinearScale, BarElement, PointElement, LineElement)

defineOptions({
  name: 'ReportTranscribeFile',
  layout
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  totalUploadedFileByTranscriptionStatus: { type: Array, default: null }
})

onMounted(() => {
  data.datasets[0].data = props.totalUploadedFileByTranscriptionStatus.map(item => item.count)

  data.labels = props.totalUploadedFileByTranscriptionStatus.map(item => {
    const dictionaryFileStatus = type => {
      if (type === 'TRANSCRIBED') {
        return 'فایل های پردازش شده'
      } else {
        return 'فایل های پردازش نشده'
      }
    }
    return dictionaryFileStatus(item?.status_group)
  })
})

const data = reactive({
  labels: [],
  datasets: [
    {
      data: [],
      backgroundColor: ['#22c55e', '#b91c1c']
    }
  ]
})

const options = reactive({
  plugins: {
    legend: {
      position: 'bottom'
    },
    title: {
      display: true
    }
  }
})
</script>
