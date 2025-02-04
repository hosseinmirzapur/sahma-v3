<template>
  <div class="p-1">
    <div class="p-4 bg-white shadow-cardUni rounded-lg">
      <h2
        class="text-right text-xl font-bold mt-0 mb-2"

        v-text="`کاربران آنلاین`" />
      <div class="flex justify-between items-start">
        <div class="w-1/5">
          <div
            v-for="(result, i) in reportResult"
            :key="i"
            class="flex flex-col items-center gap-y-3 mt-12">
            <p
              class="text-base font-normal text-gray-500"
              v-text="result.title" />
            <p
              class="text-3xl font-bold text-primaryText text-left "
              v-text="result.percent + ' نفر '" />
          </div>
        </div>

        <div class="w-4/5 relative w-full p-5">
          <div
            v-if="isZero"
            class="absolute w-full h-full flex items-center justify-center md:text-xl text-sm">
            <p class="bg-primary border-secondPrimary py-2 px-4 rounded-md shadow shadow-xl text-white text-center">
              کاربری موجود نیست
            </p>
          </div>
          <Line
            :class="isZero? 'opacity-20' : '' "
            :chart-data="data"
            :chart-options="options " />
        </div>
      </div>
    </div>

    <!-- table -->
    <div class="relative rounded-t-2xl mt-10 mx-5">
      <div class="flex gap-x-5">
        <!--  sort table -->
        <div class="relative">
          <button
            type="button"
            class="w-10 p-2 text-primaryText cursor-pointer hover:hover:bg-primary/5 hover:rounded-xl"
            @mouseover="isTooltipFilter =false"
            @mouseout="isTooltipFilter =false"
            @click.stop.prevent="isDropDownFilter = true">
            <AdjustmentsHorizontalIcon class="w-7 pointer-events-none" />
          </button>
          <!--     dropDown sort table     -->
          <div
            v-if="isDropDownFilter"
            class="z-10 absolute top-12 bg-white divide-y divide-gray-100 rounded-lg shadow-dropDownUni w-36">
            <ul class="py-2 text-sm text-gray-800 shadow-cardUni">
              <li
                v-for="(item, indexFilter) in optionFilter"
                :key="indexFilter">
                <button
                  class="block px-4 py-2 w-full hover:bg-gray-100 text-center "
                  @click.stop.prevent="choiceFunFilter(indexFilter)"
                  v-text="item" />
              </li>
            </ul>
          </div>
          <!-- tooltip process-->
          <transition name="tooltip">
            <div
              v-if="isTooltipFilter"
              class="absolute w-max top-10 w-full right-0 z-10 p-5 text-sm font-medium text-primary transition-opacity duration-300 bg-white rounded-md tooltip">
              <span v-text="`مرتب سازی`" />
            </div>
          </transition>
        </div>
        <!--  reports download -->
        <div class="relative">
          <button
            type="button"
            class="w-10 p-2 text-primaryText cursor-pointer hover:hover:bg-primary/5 hover:rounded-xl"
            @mouseover="isTooltipExcel =true"
            @mouseout="isTooltipExcel =false"
            @click.stop.prevent="downloadReports">
            <DocumentTextIcon class="w-7 pointer-events-none" />
          </button>
          <!-- tooltip process-->
          <transition name="tooltip">
            <div
              v-if="isTooltipExcel"
              class="absolute w-max top-10 w-full right-0 z-10 p-5 text-sm font-medium text-primary transition-opacity duration-300 bg-white rounded-md tooltip">
              <span v-text="`فایل اکسل`" />
            </div>
          </transition>
        </div>
      </div>

      <table class="w-full">
        <!--      header -->
        <thead class="text-base bg-white h-16 rounded-t-2xl text-start">
          <tr class="w-full text-gray-500 mb-10 rounded-t-2xl">
            <th
              v-for="(item, i) in column"
              :key="i"
              scope="col"
              class="px-2 py-3">
              <div class="flex justify-start items-center">
                {{ item }}
              </div>
            </th>
          </tr>
        </thead>
        <!--body-->
        <tbody class="relative">
          <template
            v-for="(report, i) in currReports"
            :key="i">
            <tr
              v-for="(listReport ,indexReport ) in report"
              :key="indexReport"
              class="h-16 rounded-2xl p-5 text-base cursor-default bg-white border-t border-gray-100 cursor-pointer
               hover:bg-primaryDark/5 text-start"
              @click.stop.prevent="openTooltipTable(indexReport)">
              <!-- badge online-->
              <td class="relative font-medium text-gray-700 px-2 ">
                <div
                  v-if="listReport?.loginDates.length > listReport?.logoutDates.length"
                  class="absolute inline-flex items-center justify-center w-2 h-2 bg-green-500
                  rounded-full -top-1.5 -right-2" />
                <p
                  class="text-center cursor-pointer text-start"
                  v-text="listReport.name" />
              </td>
              <td class="relative font-medium text-gray-700 px-2 ">
                <p
                  class="text-center cursor-pointer text-start"
                  v-text="listReport.personalId" />
              </td>
              <td class="relative font-medium text-gray-700">
                <template
                  v-for="(dep, c) in listReport.departments"
                  :key="c">
                  <div
                    class="bg-green-100/50 mx-1 px-1 inline-flex flex-wrap rounded-md"
                    v-text="dep.name" />
                </template>
              </td>

              <td class="relative font-medium text-gray-700 px-2 ">
                <p
                  dir="ltr"
                  class="cursor-pointer text-end"
                  v-text="listReport?.loginDates[0]" />
              </td>
              <td class="relative font-medium text-gray-700 px-2 ">
                <p
                  dir="ltr"
                  class="cursor-pointer text-end"
                  v-text="listReport?.loginDates.length > listReport?.logoutDates.length ?
                    '-' :
                    listReport?.logoutDates.at(-1) " />
              </td>
              <transition name="tooltipTable">
                <div
                  v-if="isTooltip[indexReport]"
                  class="absolute w-72 bottom-16 left-0 z-10 p-5 text-sm font-medium text-primary transition-opacity
                duration-300 bg-white rounded-md tooltip">
                  <div class="grid grid-cols-2">
                    <div class="flex flex-col gap-y-2">
                      <p v-text="`زمان ورود`" />
                      <p
                        v-for="(login, indexLogin) in listReport?.loginDates"
                        :key="indexLogin"
                        v-text="login" />
                    </div>
                    <div class="flex flex-col gap-y-2">
                      <p v-text="`زمان خروج`" />
                      <p
                        v-for="(logout, indexLogout) in listReport?.logoutDates"
                        :key="indexLogout"
                        v-text="logout" />
                    </div>
                  </div>
                </div>
              </transition>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import layout from '../../../Layouts/~AppLayout.vue'
import { AdjustmentsHorizontalIcon, DocumentTextIcon } from '@heroicons/vue/24/outline'
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
import { Line } from 'vue-chartjs'
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { Inertia } from '@inertiajs/inertia'

Chart.defaults.font.family = 'IRANSans'
Chart.register(ArcElement, Title, Tooltip, Legend, CategoryScale, LinearScale, BarElement, PointElement, LineElement)

defineOptions({
  name: 'UserReport',
  layout
})

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  reportHistory: { type: Number, default: null },
  onlineUsersToday: { type: Number, default: null },
  reports: { type: Object, required: true },
  lastWeekCountReport: { type: Number, default: null },
  lastMonthCountReport: { type: Number, default: null },
  reportFileInfo: { type: Object, required: true }
})

const column = ref(['نام و نام خانوادگی', 'کد پرسنلی', 'واحد', 'زمان ورود', 'زمان خروج'])
const reportResult = ref([
  { title: 'امروز', percent: props?.onlineUsersToday },
  { title: 'هفته گذشته', percent: props?.lastWeekCountReport },
  { title: 'ماه گذشته', percent: props?.lastMonthCountReport }
])

// for chart or top data
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
const data = reactive({
  labels: Object.keys(props.reportHistory).map((key) => {
    const date = new Date(1000 * key)
    const options = { weekday: 'long', locale: 'fa-IR' }
    return new Intl.DateTimeFormat('fa-IR', options).format(date)
  }),
  datasets: [
    {
      data: Object.values(props.reportHistory),
      backgroundColor: '#2A3875'
    }
  ]
})
// end top data

const isTooltipFilter = ref(false)
const isDropDownFilter = ref(false)
const isTooltipExcel = ref(false)
const currDate = ref(null)
const isTooltip = ref([])

const isZero = ref(Object.values(props?.reportHistory).every(element => element === 0))

const optionFilter = {
  today: 'امروز',
  yesterday: 'دیروز',
  lastWeek: 'هفته گذشته',
  lastMonth: 'ماه گذشته'
}

function downloadForUrl () {
  const link = document.createElement('a')
  link.href = props.reportFileInfo?.downloadUrl
  link.target = '_blank' // Open the link in a new tab/window
  link.download = `${props.reportFileInfo?.reportFileName}.xlsx` // Specify the desired download filename

  link.click()
}

const currReports = computed(() => {
  return Object.values(props.reports).map(v => {
    return Object.values(v).map(value => value)
  })
})

// functions
function openTooltipTable (i) {
  closeDropdown()
  isTooltip.value[i] = !isTooltip.value[i]
}

function choiceFunFilter (indexFilter) {
  currDate.value = indexFilter
  Inertia.visit(route('web.user.report.users', { onlineUserReportTypeByDate: indexFilter }), {
    method: 'get',
    replace: true,
    preserveState: true,
    onSuccess: () => {
      isDropDownFilter.value = false
    }
  })
}

function downloadReports () {
  Inertia.visit(route('web.user.report.create-excel-users', { onlineUserReportTypeByDate: currDate.value }), {
    method: 'get',
    replace: true,
    preserveState: true,
    onSuccess: () => {
      if (!props.reportFileInfo) return
      downloadForUrl()
    }
  })
}

function closeDropdown () {
  isDropDownFilter.value = false
  isTooltip.value.fill(false)
}

onMounted(() => {
  // Attach the event listener
  window.addEventListener('click', closeDropdown)
})

onBeforeUnmount(() => {
// Cleanup when the component is unmounted
  window.removeEventListener('click', closeDropdown)
})

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
  transform: translateX(10px);
  opacity: 0;
}

.tooltipTable-enter-active {
  transition: all 0.5s ease-out;
}
.tooltipTable-leave-active {
  transition: all 0.1s cubic-bezier(1, 0.5, 0.8, 1);
}
.tooltipTable-enter-from,
.tooltipTable-leave-to {
  transform: translateY(10px);
  opacity: 0;
}
</style>
