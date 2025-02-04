<template>
  <div class="p-1">
    <div class="p-4 bg-white shadow-cardUni rounded-lg">
      <h2
        class="w-full text-right text-xl font-bold mt-0 mb-2"

        v-text="`تعداد اسناد بارگذاری شده`" />
      <div class="flex justify-between items-start">
        <div class="w-1/5">
          <div
            v-for="(result, i) in reportResult"
            :key="i"
            class="flex flex-col items-center gap-y-3 mt-14">
            <p
              class="text-base font-normal text-gray-500"
              v-text="result.title" />
            <p
              class="text-3xl font-bold text-primaryText text-left "
              v-text="result.percent + ' فایل '" />
          </div>
        </div>

        <div class="w-4/5 relative w-full p-5">
          <div
            v-if="isZero"
            class="absolute w-full h-full flex items-center justify-center md:text-xl text-sm">
            <p class="bg-primary border-secondPrimary py-2 px-4 rounded-md shadow shadow-xl text-white text-center">
              سندی موجود نیست
            </p>
          </div>
          <Line
            :class="isZero? 'opacity-20' : '' "
            :chart-data="data"
            :chart-options="options " />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import layout from '../../../Layouts/~AppLayout.vue'
import { Line } from 'vue-chartjs'
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
import { reactive, ref } from 'vue'

Chart.defaults.font.family = 'IRANSans'
Chart.register(ArcElement, Title, Tooltip, Legend, CategoryScale, LinearScale, BarElement, PointElement, LineElement)

defineOptions({
  name: 'Report',
  layout
})
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  historyTotalFilesIn: { type: Number, default: null },
  totalTodayFiles: { type: Number, default: null },
  lastWeekCountFiles: { type: Number, default: null },
  lastMonthCountFiles: { type: Number, default: null }
})

const reportResult = ref([
  { title: 'امروز', percent: props?.totalTodayFiles },
  { title: 'هفته گذشته', percent: props?.lastWeekCountFiles },
  { title: 'ماه گذشته', percent: props?.lastMonthCountFiles }
])

const isZero = ref(Object.values(props?.historyTotalFilesIn).every(element => element === 0))

const data = reactive({
  labels: Object.keys(props.historyTotalFilesIn).map((key) => {
    return Intl.DateTimeFormat('fa-IR', {
      dateStyle: 'short'
    }).format(Number(1000 * key))
  }),
  datasets: [
    {
      data: Object.values(props.historyTotalFilesIn),
      backgroundColor: '#2A3875'
    }
  ]
})

const options = reactive({
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    y: {
      ticks: {
        stepSize: 1, // Set the step size for the x-axis
        beginAtZero: true, // Start the y-axis from zero
        suggestedMin: 0 // Set the minimum value explicitly to zero
      }
    }
  },
  plugins: {
    legend: {
      display: false
    },
    title: {
      display: true
    },
    scales: {
      x: {
        ticks: {
          stepSize: 1 // Set the step size for the x-axis
        }
      },
      y: {
        min: 0,
        ticks: {
          stepSize: 1,
          callback: function (value) {
            return value.toString()
          }
        }
      }
    }
  }
})
</script>
