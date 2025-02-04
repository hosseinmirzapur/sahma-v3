<template>
  <div class="m-2 bg-white shadow-cardUni rounded-lg flex justify-center item center">
    <div class="w-1/2 p-5 mt-20">
      <Doughnut
        :chart-data="data"
        :chart-options="options" />
    </div>
  </div>
</template>

<script setup>
import layout from '../../../Layouts/~AppLayout.vue'
import { dictionary } from '../../../globalFunction/dictionary.js'
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
import { onMounted, reactive } from 'vue'

Chart.defaults.font.family = 'IRANSans'
Chart.register(ArcElement, Title, Tooltip, Legend, CategoryScale, LinearScale, BarElement, PointElement, LineElement)

defineOptions({
  name: 'ReportFileType',
  layout
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  totalUploadedFileGroupByType: { type: Array, default: null }
})

onMounted(() => {
  data.datasets[0].data = props.totalUploadedFileGroupByType.map(item => item.count)

  data.labels = props.totalUploadedFileGroupByType.map(item => {
    return dictionary(item.type)
  })
})

const data = reactive({
  labels: [],
  datasets: [
    {
      data: [],
      backgroundColor: ['#14b8a6', '#b91c1c', '#f97316', '#15803d']
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
