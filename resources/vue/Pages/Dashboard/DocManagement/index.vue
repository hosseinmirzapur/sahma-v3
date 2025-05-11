<template>
  <div class="p-5 min-h-[calc(100vh-9rem)]">
    <ToolBar
      v-model="textSearch"
      :is-refresh="isRefreshIcon"
      :is-active="form.files.length > 0 || form.folders.length > 0"
      :is-check-box="isCheckBoxing"
      @item-click="getItemInToolbar"
    />

    <Breadcrumb class="mt-2" :headers="breadcrumbs" />

    <ul
      v-if="searchFolder.length > 0 || searchFiles.length > 0"
      class="flex flex-wrap gap-5 mt-5"
    >
      <!-- folders -->
      <template v-if="searchFolder.length > 0">
        <div
          v-for="doc in searchFolder"
          :key="'folder-' + doc.id"
          class="relative flex items-center cursor-pointer border border-transparent hover:border hover:shadow-cardUni rounded-xl px-2 py-1"
          :class="{
            '!border-primary/20 ': isDropDowns[doc.id],
            '!border-primary/20': form.folders.includes(doc?.id),
          }"
          @contextmenu.prevent="openDropDown(doc?.id, 'folder')"
          @dblclick="
            subOpenFolder('open', 'web.user.dashboard.folder.show', doc)
          "
          @click="!isCheckBoxing ? funSelectItem(doc?.id, 'folder') : null"
        >
          <input
            :id="'checkbox-folder-' + doc?.id"
            v-model="form.folders"
            :value="doc?.id"
            type="checkbox"
            class="peer ml-3"
            :class="{ invisible: !isCheckBoxing }"
          />

          <label
            class="flex items-center gap-x-4 cursor-pointer"
            :for="'checkbox-folder-' + doc?.id"
          >
            <div
              class="flex justify-center items-center w-14 h-14 bg-primary/5 text-gray-600 rounded-xl"
            >
              <FolderIcon class="w-8" />
            </div>
            <p class="text-lg cursor-pointer select-none" v-text="doc.name" />
          </label>

          <!-- drop Down folder -->
          <div
            v-if="isDropDowns[doc?.id]"
            class="z-10 absolute top-12 left-0 bg-white divide-y divide-gray-100 rounded-lg shadow-dropDownUni w-44"
          >
            <ul class="py-2 text-sm text-gray-800">
              <li v-for="(item, j) in options" :key="'folder-opt-' + j">
                <button
                  :id="'folder-action-' + item.slug"
                  class="block px-4 py-2 w-full hover:bg-gray-100 text-center"
                  :class="{
                    hidden: item.slug !== 'open' && authUser?.isUser, // Keep specific hide logic
                    'disabled:cursor-not-allowed disabled:opacity-50':
                      checkMultiSelectItem(item), // Remove hidden class here as disabled state implies it
                  }"
                  :disabled="checkMultiSelectItem(item)"
                  @click.prevent="
                    choiceFunctionSubmit(item.slug, item.link, doc, 'folder')
                  "
                >
                  {{ item?.title }}
                </button>
              </li>
            </ul>
          </div>
        </div>
      </template>

      <!-- files -->
      <template v-if="searchFiles.length > 0">
        <div
          v-for="doc in searchFiles"
          :key="'file-' + doc.id"
          class="relative flex items-center cursor-pointer border border-transparent hover:border hover:shadow-cardUni rounded-xl px-2 py-1"
          :class="{
            '!border-primary/20 ': isDropDownsFiles[doc.id],
            // 'shadow-cardUni': isSelectItemFolder === doc.id, // Removed isSelectItemFolder
            '!border-primary/20': form.files.includes(doc?.id),
          }"
          @contextmenu.prevent="openDropDown(doc?.id, 'file')"
          @dblclick="subOpenFiles('open', 'web.user.dashboard.file.show', doc)"
          @click="!isCheckBoxing ? funSelectItem(doc?.id, 'file') : null"
        >
          <input
            :id="'checkbox-file-' + doc?.id"
            v-model="form.files"
            :value="doc?.id"
            type="checkbox"
            class="peer ml-3"
            :class="{ invisible: !isCheckBoxing }"
          />

          <label
            class="relative flex items-center gap-x-4 cursor-pointer"
            :for="'checkbox-file-' + doc?.id"
          >
            <!-- status icons-->
            <ClockIcon
              v-if="doc?.status === 'STATUS_WAITING_FOR_MANUAL_PROCESS'"
              class="w-5 absolute -top-1 -right-2 shadow-lg text-blue-600 rounded-full animate-pulse"
              title="در انتظار پردازش دستی"
            />
            <SparklesIcon
              v-if="doc?.status === 'WAITING_FOR_TRANSCRIPTION'"
              class="w-5 absolute -top-1 -right-2 shadow-lg text-purple-600 rounded-full animate-pulse"
              title="در حال تبدیل گفتار به متن"
            />
            <ClockIcon
              v-if="
                doc?.status === 'WAITING_FOR_AUDIO_SEPARATION' ||
                doc?.status === 'WAITING_FOR_SPLIT'
              "
              class="w-5 absolute -top-1 -right-2 shadow-lg text-orange-500 rounded-full"
              title="در حال پردازش اولیه صوت"
            />
            <CheckCircleIcon
              v-if="doc?.status === 'TRANSCRIBED'"
              class="w-5 absolute -top-1 -right-2 shadow-lg text-green-700 rounded-full"
              title="پردازش انجام شد"
            />
            <ExclamationCircleIcon
              v-if="doc?.status === 'WAITING_FOR_RETRY'"
              class="w-5 absolute -top-1 -right-2 shadow-lg text-yellow-400 rounded-full"
              title="در انتظار تلاش مجدد"
            />
            <XCircleIcon
              v-if="doc?.status === 'REJECTED'"
              class="w-5 absolute -top-1 -right-2 shadow-lg text-red-500 rounded-full"
              title="پردازش رد شد"
            />
            <!-- type files icons-->
            <div
              class="flex justify-center items-center w-14 h-14 rounded-xl"
              :class="setBgFiles(doc?.type)"
            >
              <DocumentIcon
                v-if="doc?.type === 'pdf' || doc?.type === 'image'"
                class="w-8"
              />
              <DocumentWordIcon v-if="doc?.type === 'word'" class="w-8" />
              <MicrophoneIcon v-if="doc?.type === 'voice'" class="w-8" />
              <VideoCameraIcon v-if="doc?.type === 'video'" class="w-8" />
              <DocumentExcelIcon
                v-if="doc?.type === 'spreadsheet'"
                class="w-8"
              />
              <DocumentPowerpointIcon
                v-if="doc?.type === 'powerpoint'"
                class="w-8"
              />
              <DocumentZipIcon v-if="doc?.type === 'archive'" class="w-8" />
            </div>
            <p class="text-lg select-none cursor-pointer" v-text="doc.name" />
          </label>

          <!-- drop Down file -->
          <div
            v-if="isDropDownsFiles[doc?.id]"
            class="z-10 absolute top-12 left-0 bg-white divide-y divide-gray-100 rounded-lg shadow-dropDownUni cursor-pointer w-44"
          >
            <ul class="py-2 text-sm text-gray-800">
              <li v-for="(item, j) in optionsFile" :key="'file-opt-' + j">
                <button
                  :id="'file-action-' + item.slug"
                  class="block w-full px-4 py-2 hover:bg-gray-100 text-center"
                  :class="{
                    hidden: item.slug !== 'open' && authUser?.isUser, // Keep specific hide logic
                    'disabled:cursor-not-allowed disabled:opacity-50':
                      checkMultiSelectItem(item), // Remove hidden class here
                  }"
                  type="button"
                  :disabled="checkMultiSelectItem(item)"
                  @click.prevent="
                    choiceFunctionSubmit(item.slug, item.link, doc, 'file')
                  "
                >
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
        :title="currFolder.length > 0 ? 'تغییر نام پوشه' : 'تغییر نام فایل'"
        @close="resetAction"
      >
        <form class="w-full" @submit.prevent="subRename">
          <div
            class="flex items-center border-b border-primary py-2"
            :class="{ 'border-red-600': errors.length > 0 }"
          >
            <input
              v-if="currFolder.length > 0"
              v-model="form.folderName"
              class="appearance-none bg-transparent border-none focus:border-none w-full text-gray-700 py-1 px-2 leading-tight focus:outline-none shadow-transparent"
              :class="{ 'placeholder-red-400': errors.length > 0 }"
              type="text"
              placeholder="تغییر نام پوشه "
            />

            <input
              v-if="currFile.length > 0"
              v-model="baseNameFile"
              class="appearance-none bg-transparent border-none focus:border-none w-full text-gray-700 py-1 px-2 leading-tight focus:outline-none shadow-transparent"
              :class="{ 'placeholder-red-400': errors.length > 0 }"
              type="text"
              placeholder="تغییر نام فایل"
            />

            <button
              class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark text-sm border-4 text-white py-1 px-2 rounded"
              type="submit"
              v-text="'ویرایش'"
            />
          </div>
          <ul class="list-inside text-sm text-red-600 pt-2">
            <li
              v-for="(e, i) in errors"
              :key="'rename-err-' + i"
              class="text-start"
              v-text="e"
            />
          </ul>
        </form>
      </Modal>

      <!-- archive modal file and folder -->
      <Modal
        :is-open="isModalArchive"
        :title="currFolder.value?.length > 0 ? 'آرشیو پوشه' : 'آرشیو فایل'"
        @close="resetAction"
      >
        <form class="w-full" @submit.prevent="subArchive">
          <p v-if="currFolder.length > 0">
            آیا از آرشیو پوشه
            <span
              v-for="(folder, i) in currFolder"
              :key="'arch-folder-' + i"
              class="font-bold px-1"
              v-text="folder?.name"
            />
            {{ currFolder.length > 1 ? "ها" : "" }} مطمئن هستید؟
          </p>
          <p v-if="currFile.length > 0">
            آیا از آرشیو فایل
            <span
              v-for="(file, i) in currFile"
              :key="'arch-file-' + i"
              class="font-bold px-1"
              v-text="file?.name"
            />
            {{ currFile.length > 1 ? "ها" : "" }} مطمئن هستید؟
          </p>
          <button
            class="w-full flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark text-sm border-4 text-white py-1 px-2 rounded-xl mt-5"
            type="submit"
            v-text="`بله، آرشیو شود`"
          />
          <ul
            v-if="errors?.length > 0"
            class="list-inside text-sm text-red-600 pt-2"
          >
            <li
              v-for="(e, i) in errors"
              :key="'arch-err-' + i"
              class="text-start"
              v-text="e"
            />
          </ul>
        </form>
      </Modal>

      <!-- delete modal file and folder -->
      <Modal
        :is-open="isModalDelete"
        :title="currFolder.value?.length > 0 ? 'حذف پوشه' : 'حذف فایل'"
        @close="resetAction"
      >
        <form class="w-full" @submit.prevent="subDelete">
          <p v-if="currFolder.length > 0">
            آیا از حذف پوشه
            <span
              v-for="(folder, i) in currFolder"
              :key="'del-folder-' + i"
              class="font-bold px-1"
              v-text="folder?.name"
            />
            {{ currFolder.length > 1 ? "ها" : "" }} مطمئن هستید؟ (این عمل
            غیرقابل بازگشت است)
          </p>
          <p v-if="currFile.length > 0">
            آیا از حذف فایل
            <span
              v-for="(file, i) in currFile"
              :key="'del-file-' + i"
              class="font-bold px-1"
              v-text="file?.name"
            />
            {{ currFile.length > 1 ? "ها" : "" }} مطمئن هستید؟ (این عمل غیرقابل
            بازگشت است)
          </p>
          <button
            class="w-full flex-shrink-0 bg-red-600 hover:bg-red-700 border-red-600 hover:border-red-700 text-sm border-4 text-white py-1 px-2 rounded-xl mt-5"
            type="submit"
            v-text="`بله، حذف شود`"
          />
          <ul
            v-if="errors?.length > 0"
            class="list-inside text-sm text-red-600 pt-2"
          >
            <li
              v-for="(e, i) in errors"
              :key="'del-err-' + i"
              class="text-start"
              v-text="e"
            />
          </ul>
        </form>
      </Modal>

      <!-- move modal folder and file-->
      <Modal :is-open="isModalMove" :title="`انتقال`" @close="resetAction">
        <span
          v-if="errorMessage"
          class="block text-right text-red-600 text-sm pb-2"
          v-text="errorMessage"
        />
        <div class="p-3 text-sm text-right z-30">
          <span> انتقال </span>
          <template v-if="currFolder.length > 0">
            پوشه
            <span
              v-for="(folder, i) in currFolder"
              :key="'move-curr-folder-' + i"
              class="font-bold px-1 bg-green-50"
              v-text="folder?.name + (i < currFolder.length - 1 ? '، ' : '')"
            />
          </template>
          <template v-if="currFile.length > 0">
            {{ currFolder.length > 0 ? " و " : "" }} فایل
            <span
              v-for="(file, j) in currFile"
              :key="'move-curr-file-' + j"
              class="font-bold px-1 bg-green-50"
              v-text="file?.name + (j < currFile.length - 1 ? '، ' : '')"
            />
          </template>
          <span> به </span>
          <span class="font-bold px-2 inline bg-red-50">
            {{ formModal.destination.name }}
          </span>
        </div>
        <div
          class="h-70 overflow-auto bg-gray-100/50 shadow-inner rounded-xl p-5"
        >
          <button
            class="w-full pb-2 text-sm text-right hover:text-primary"
            :class="{
              'text-secondPrimary font-semibold': !formModal.destination.id,
            }"
            @click.prevent="setDesDashboard"
          >
            داشبورد (ریشه)
          </button>
          <MoveFolder
            :destination="formModal.destination"
            :folders="authUser.folders"
            @data-to-parent="handleDataFromChild"
          />
        </div>
        <button
          class="w-full flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark text-sm border-4 h-12 shadow-btnUni text-white py-1 px-2 mt-5 rounded-xl cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          @click="subMove()"
          v-text="`انتقال`"
        />
      </Modal>

      <!-- copy modal folder and file-->
      <Modal :is-open="isModalCopy" :title="`کپی`" @close="resetAction">
        <span
          v-if="errorMessage"
          class="block text-right text-red-600 text-sm pb-2"
          v-text="errorMessage"
        />
        <div class="p-3 text-sm text-right z-30">
          <span> کپی </span>
          <template v-if="currFolder.length > 0">
            پوشه
            <span
              v-for="(folder, i) in currFolder"
              :key="'copy-curr-folder-' + i"
              class="font-bold px-1 bg-green-50"
              v-text="folder?.name + (i < currFolder.length - 1 ? '، ' : '')"
            />
          </template>
          <template v-if="currFile.length > 0">
            {{ currFolder.length > 0 ? " و " : "" }} فایل
            <span
              v-for="(file, j) in currFile"
              :key="'copy-curr-file-' + j"
              class="font-bold px-1 bg-green-50"
              v-text="file?.name + (j < currFile.length - 1 ? '، ' : '')"
            />
          </template>
          <span> در </span>
          <span class="font-bold px-2 inline bg-red-50">
            {{ formModal.destination.name }}
          </span>
        </div>
        <div
          class="h-70 overflow-auto bg-gray-100/50 shadow-inner rounded-xl p-5"
        >
          <button
            class="w-full pb-2 text-sm text-right hover:text-primary"
            :class="{
              'text-secondPrimary font-semibold': !formModal.destination.id,
            }"
            @click.prevent="setDesDashboard"
          >
            داشبورد (ریشه)
          </button>
          <MoveFolder
            :destination="formModal.destination"
            :folders="authUser.folders"
            @data-to-parent="handleDataFromChild"
          />
        </div>
        <button
          class="w-full flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark text-sm border-4 h-12 shadow-btnUni text-white py-1 px-2 mt-5 rounded-xl cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
          @click="subCopy()"
          v-text="`کپی`"
        />
      </Modal>

      <!-- description modal file-->
      <Modal
        :is-open="isModalDescription"
        :title="`توضیحات فایل`"
        @close="resetAction"
      >
        <form class="w-full" @submit.prevent="saveDescription">
          <div class="p-3 pt-0 text-sm text-right z-30">
            <textarea
              v-if="currFile.length === 1"
              v-model="form.description"
              rows="5"
              class="w-full h-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary"
              maxlength="200"
              placeholder="توضیحات فایل (حداکثر 200 کاراکتر)"
              @input="characterLimit"
            />
            <p v-else class="text-center text-gray-500">
              برای افزودن توضیحات، لطفا دقیقا یک فایل را انتخاب کنید.
            </p>
            <p class="text-left text-xs text-gray-400 mt-1">
              {{ form.description?.length || 0 }} / 200
            </p>
          </div>
          <ul class="list-inside text-sm text-red-600 px-3">
            <li
              v-for="(e, i) in errors"
              :key="'desc-err-' + i"
              class="text-start"
              v-text="e"
            />
          </ul>
          <div class="flex justify-center gap-x-4 mt-4 px-3">
            <button
              class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary text-sm border shadow-btnUni text-white w-36 py-2 px-4 rounded-xl cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              type="submit"
              :disabled="currFile.length !== 1"
              v-text="'ثبت'"
            />
            <button
              class="flex-shrink-0 border border-primary text-primary text-sm w-36 py-2 px-4 rounded-xl hover:border-primaryDark hover:text-primaryDark"
              type="button"
              @click.prevent="resetAction"
              v-text="`انصراف`"
            />
          </div>
        </form>
      </Modal>

      <!--  modal sort-->
      <Modal :is-open="isModalSort" :title="`مرتب سازی`" @close="resetAction">
        <form class="w-full">
          <div class="flex flex-col items-start py-3 gap-y-4 px-2">
            <div
              v-for="(item, i) in sortsItem"
              :key="'sort-' + item.value"
              class="flex items-center w-full"
            >
              <input
                :id="'sort-radio-' + i"
                v-model="sortedItems"
                type="radio"
                :value="item.value"
                name="sort-radio-group"
                class="w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500 cursor-pointer"
                @change="isModalSort = false"
              />
              <label
                :for="'sort-radio-' + i"
                class="ml-2 mr-2 text-base font-medium text-primaryText cursor-pointer w-full"
                v-text="item.label"
              />
            </div>
          </div>
        </form>
      </Modal>

      <!-- Department Change Modal -->
      <Modal
        :is-open="isModalDepChange"
        :title="`ویرایش واحد سازمانی فایل`"
        @close="resetAction"
      >
        <form class="w-full" @submit.prevent="saveDepartments">
          <div
            v-if="currFile.length === 1"
            class="flex flex-col items-start relative"
          >
            <label class="block text-base text-gray-900 mb-2"
              >واحد سازمانی:</label
            >
            <div
              class="flex flex-row justify-start gap-x-1 items-center flex-wrap rounded-lg border border-gray-300 w-full min-h-[2.5rem] py-1 px-3 focus-within:ring-1 focus-within:ring-primary relative cursor-pointer"
              @click.stop.prevent="isOpenDepartments = !isOpenDepartments"
            >
              <p v-if="form.departments.length === 0" class="text-gray-400">
                انتخاب کنید...
              </p>
              <span
                v-for="(item, j) in form.departments"
                :key="'dep-selected-' + j"
                class="bg-blue-100 text-blue-800 text-xs font-medium m-0.5 px-2 py-0.5 rounded"
              >
                {{ item?.name }}
                <!-- Display name directly from object -->
              </span>
              <ChevronDownIcon class="absolute left-3 w-4 text-gray-500" />
            </div>
            <div
              v-if="isOpenDepartments"
              class="absolute left-0 right-0 top-[calc(100%+5px)] z-20 max-h-60 overflow-y-auto rounded-md border border-gray-200 shadow-lg w-full px-3 py-2 bg-white"
              @click.stop
            >
              <div
                v-for="option in departments"
                :key="'dep-option-' + option.id"
                class="flex items-center gap-x-3 py-1.5"
              >
                <input
                  :id="'dep-check-' + option.id"
                  v-model="form.departments"
                  :value="option"
                  type="checkbox"
                  class="w-4 h-4 text-blue-600 bg-gray-100 rounded border-gray-300 focus:ring-blue-500 cursor-pointer"
                />
                <label
                  :for="'dep-check-' + option.id"
                  class="text-sm font-medium text-gray-700 w-full text-start cursor-pointer"
                  v-text="option.name"
                />
              </div>
              <p
                v-if="departments.length === 0"
                class="text-sm text-gray-500 text-center py-2"
              >
                واحد سازمانی یافت نشد.
              </p>
            </div>
          </div>
          <p v-else class="text-center text-gray-500">
            برای ویرایش واحد سازمانی، لطفا دقیقا یک فایل را انتخاب کنید.
          </p>

          <ul class="list-inside text-sm text-red-600 pt-2">
            <li
              v-for="(e, i) in errors"
              :key="'dep-err-' + i"
              class="text-start"
              v-text="e"
            />
          </ul>

          <div class="flex justify-center mt-5">
            <button
              class="flex-shrink-0 bg-primary hover:bg-primaryDark border-primary hover:border-primaryDark shadow-btnUni text-sm border-4 text-white w-full py-2 px-4 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed"
              type="submit"
              :disabled="currFile.length !== 1"
            >
              تایید
            </button>
          </div>
        </form>
      </Modal>
    </ul>

    <!-- empty page-->
    <div v-else class="h-[calc(100vh-20rem)] flex justify-center items-center">
      <div
        class="w-full max-w-md rounded-xl flex flex-col items-center gap-y-8 text-lg p-5 text-center"
      >
        <svg
          class="w-32 h-auto text-gray-400"
          xmlns="http://www.w3.org/2000/svg"
          width="181"
          height="204"
          viewBox="0 0 181 204"
          fill="none"
        >
          <!-- SVG Path Data -->
          <ellipse
            opacity="0.1"
            cx="90.5"
            cy="104"
            rx="90.5"
            ry="91"
            fill="#2A3875"
          />
          <rect
            x="65.223"
            y="47.1755"
            width="96.4336"
            height="134.713"
            rx="7"
            transform="rotate(2.5 65.223 47.1755)"
            fill="#FAFAFA"
            stroke="#8AA6FF"
            stroke-width="2"
          />
          <rect
            x="9.09385"
            y="39.8598"
            width="100.434"
            height="138.713"
            rx="9"
            transform="rotate(-7.5 9.09385 39.8598)"
            fill="#FAFAFA"
            stroke="#8AA6FF"
            stroke-width="2"
          />
          <path
            d="M18.2126 40.1722L99.941 29.4125C104.048 28.8718 107.815 31.7627 108.356 35.8694L111.351 58.6171L14.7506 71.3348L11.7558 48.587C11.2151 44.4803 14.1059 40.7129 18.2126 40.1722Z"
            fill="#FAFAFA"
            stroke="#8AA6FF"
          />
          <rect
            x="129.03"
            y="31.9727"
            width="4.1014"
            height="13.6713"
            rx="2.0507"
            transform="rotate(82.5 129.03 31.9727)"
            fill="#8AA6FF"
          />
          <rect
            x="91.5762"
            y="9.32617"
            width="4.1014"
            height="13.6713"
            rx="2.0507"
            transform="rotate(-7.5 91.5762 9.32617)"
            fill="#8AA6FF"
          />
          <rect
            x="115.606"
            y="13.0566"
            width="4.1014"
            height="13.6713"
            rx="2.0507"
            transform="rotate(37.5 115.606 13.0566)"
            fill="#8AA6FF"
          />
          <rect
            x="24.5215"
            y="76.0684"
            width="79.2937"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 24.5215 76.0684)"
            fill="#EAEBF1"
          />
          <rect
            x="28.0908"
            y="103.178"
            width="79.2937"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 28.0908 103.178)"
            fill="#EAEBF1"
          />
          <rect
            x="31.6602"
            y="130.287"
            width="79.2937"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 31.6602 130.287)"
            fill="#EAEBF1"
          />
          <rect
            x="25.9492"
            y="86.9121"
            width="28.7098"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 25.9492 86.9121)"
            fill="#D1D8FF"
          />
          <rect
            x="29.5186"
            y="114.021"
            width="28.7098"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 29.5186 114.021)"
            fill="#D1D8FF"
          />
          <rect
            x="33.0869"
            y="141.129"
            width="28.7098"
            height="5.46853"
            rx="2"
            transform="rotate(-7.5 33.0869 141.129)"
            fill="#D1D8FF"
          />
        </svg>
        <p
          class="text-center text-primary/70"
          v-text="
            props.folders?.length === 0 && props.files?.length === 0
              ? `در حال حاضر سندی موجود نیست.`
              : `موردی یافت نشد.`
          "
        />
      </div>
    </div>

    <Alert
      class="z-20"
      :title="AlertOption.data"
      :is-open="AlertOption.isAlert"
      :contents-list="AlertOption.dataList"
      :status="AlertOption.status"
      @close="AlertOption.isAlert = false"
    />
  </div>
</template>

<script setup>
import layout from "../../../Layouts/~AppLayout.vue";
import Breadcrumb from "../../Components/Breadcrumb.vue";
import Modal from "../../Components/Modal.vue";
import MoveFolder from "../../Components/MoveFolder.vue";
import {
  onBeforeUnmount,
  ref,
  computed,
  onMounted,
  reactive,
  watch,
} from "vue";
import {
  ChevronDownIcon,
  DocumentIcon,
  FolderIcon,
  MicrophoneIcon,
  VideoCameraIcon,
} from "@heroicons/vue/24/outline";
import {
  ClockIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  XCircleIcon,
  SparklesIcon,
} from "@heroicons/vue/24/solid";
import { useForm, usePage } from "@inertiajs/inertia-vue3";
import { Inertia } from "@inertiajs/inertia";
import ToolBar from "../../Components/ToolBar.vue";
import Alert from "../../Components/Alert.vue";
import DocumentWordIcon from "../../Components/icon/DocumentWordIcon.vue";
import DocumentExcelIcon from "../../Components/icon/DocumentExcelIcon.vue";
import DocumentPowerpointIcon from "../../Components/icon/DocumentPowerpointIcon.vue";
import DocumentZipIcon from "../../Components/icon/DocumentZipIcon.vue";

// eslint-disable-next-line no-undef
defineOptions({
  name: "Index",
  layout,
});

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const props = defineProps({
  folders: { type: Array, required: true }, // Use Array type
  files: { type: Array, required: true }, // Use Array type
  authUser: { type: Object, required: true },
  breadcrumbs: { type: Array, default: () => [] },
  zipFileInfo: { type: Object, required: false, default: null }, // Made optional
  departments: { type: Array, required: true },
});

const options = ref([
  { title: "ورود", slug: "open", link: "web.user.dashboard.folder.show" },
  {
    title: "تغییر نام",
    slug: "rename",
    link: "web.user.dashboard.folder.rename",
  },
  { title: "انتقال", slug: "move", link: "#" }, // Link adjusted later
  { title: "کپی", slug: "copy", link: "#" }, // Link adjusted later
  { title: "دانلود", slug: "download", link: "#" },
  { title: "آرشیو", slug: "archive", link: "#" },
  { title: "حذف", slug: "delete", link: "#" },
]);
const optionsFile = ref([
  { title: "باز کردن", slug: "open", link: "web.user.dashboard.file.show" },
  {
    title: "تغییر نام",
    slug: "rename",
    link: "web.user.dashboard.file.rename",
  },
  { title: "ویرایش واحد سازمانی", slug: "depChange", link: "#" },
  { title: "انتقال", slug: "move", link: "#" },
  { title: "کپی", slug: "copy", link: "#" },
  { title: "دانلود", slug: "download", link: "#" },
  { title: "آرشیو", slug: "archive", link: "#" },
  { title: "حذف", slug: "delete", link: "#" },
  { title: "توضیحات", slug: "description", link: "#" },
]);
const sortsItem = ref([
  { label: "بر اساس تاریخ", value: "date" },
  { label: "بر اساس حروف الفبا", value: "string" },
]);

// const isSelectItemFolder = ref(null); // Removed
const isDropDowns = ref({}); // Changed to object
const isDropDownsFiles = ref({}); // Changed to object
const isCheckBoxing = ref(false);
const isModalMove = ref(false);
const isModalCopy = ref(false);
const isModalRename = ref(false);
const isModalArchive = ref(false);
const isModalDelete = ref(false);
const isModalDescription = ref(false);
const isModalSort = ref(false);
const isModalDepChange = ref(false);
const isRefreshIcon = ref(false);
const isOpenDepartments = ref(false);

const form = useForm({
  folderName: "",
  fileName: "", // Added fileName
  folders: [],
  files: [],
  destinationFolder: null,
  description: "",
  departments: [], // Will hold department OBJECTS when modal is open
});
const formModal = useForm({
  destination: {
    name: "داشبورد",
    id: "",
  },
});
const errors = ref([]); // Use for form errors display
const currFolder = ref([]); // Holds full folder objects based on form.folders IDs
const currFile = ref([]); // Holds full file objects based on form.files IDs
const baseNameFile = ref("");
const extensionFile = ref("");
const textSearch = ref(""); // Default to empty string
const sortedItems = ref("date"); // Default sort
const { url } = usePage();
const AlertOption = reactive({
  isAlert: false,
  dataList: [],
  status: "info", // Default status
  data: "",
});
const errorMessage = ref(""); // Specific for move/copy errors

// Watch for changes in selection to update currFolder/currFile
watch(
  [() => form.folders, () => form.files],
  () => {
    // This watcher is potentially heavy if lists are large.
    // Consider calling setCurrItems only when needed (e.g., before opening modals)
    // Keeping it for now as it matches implicit original behavior of needing currItems updated.
    setCurrItems();
  },
  { deep: true },
);

onMounted(() => {
  window.addEventListener("click", handleGlobalClick);
  window.addEventListener("keydown", funShortKeys);
});

onBeforeUnmount(() => {
  window.removeEventListener("click", handleGlobalClick);
  window.removeEventListener("keydown", funShortKeys);
});

// Closes dropdowns if click is outside relevant elements
function handleGlobalClick(event) {
  // Check if click is outside dropdown triggers or dropdown content
  const clickedDropdownTrigger = event.target.closest(
    "[data-dropdown-trigger]",
  );
  const clickedDropdownContent = event.target.closest(
    "[data-dropdown-content]",
  );
  const clickedDepartmentDropdown = event.target.closest(
    "#department-dropdown-trigger, #department-dropdown-content",
  );

  if (!clickedDropdownTrigger && !clickedDropdownContent) {
    closeDropdown(); // Close file/folder dropdowns
  }
  if (!clickedDepartmentDropdown) {
    isOpenDepartments.value = false; // Close department dropdown
  }
}

// general functions
function checkMultiSelectItem(item) {
  const mergeForm = [...form.files, ...form.folders];
  // Disable actions unsuitable for multiple items
  return (
    mergeForm.length > 1 &&
    (item?.slug === "open" ||
      item?.slug === "rename" ||
      item?.slug === "description" ||
      item?.slug === "depChange") // Added depChange
  );
}

function funShortKeys(event) {
  // Check if focus is inside an input/textarea to avoid conflicts
  if (
    document.activeElement instanceof HTMLInputElement ||
    document.activeElement instanceof HTMLTextAreaElement
  ) {
    return;
  }

  if (event.shiftKey && event.key === "A") {
    event.preventDefault();
    checkedAllItems();
  }
  if (event.shiftKey && event.key === "C") {
    event.preventDefault();
    openModalsCopy();
  }
  if (event.shiftKey && event.key === "M") {
    event.preventDefault();
    openModalsMove();
  }
  if (event.shiftKey && event.key === "R") {
    event.preventDefault();
    openModalArchive();
  }
  if (event.ctrlKey && event.key === "a") {
    event.preventDefault();
    subCheckBox();
  } // Use Ctrl+A for checkbox toggle? Original was just Control key press. Changed to Ctrl+A for Select All analogy.
  if (event.key === "Delete") {
    event.preventDefault();
    openModalDelete();
  }
}

function checkedAllItems() {
  if (isCheckBoxing.value) {
    const allFolderIds = props.folders.map((v) => v.id);
    const allFileIds = props.files.map((v) => v.id);

    const allSelected =
      form.folders.length === allFolderIds.length &&
      form.files.length === allFileIds.length &&
      allFolderIds.every((id) => form.folders.includes(id)) &&
      allFileIds.every((id) => form.files.includes(id));

    if (allSelected) {
      form.reset(); // Deselect all
    } else {
      form.folders = [...allFolderIds]; // Select all
      form.files = [...allFileIds];
    }
  } else {
    setAlerts(
      true,
      "info",
      "برای انتخاب همه، ابتدا حالت انتخاب چندتایی (Ctrl+A) را فعال کنید.",
    );
  }
}

const handleDataFromChild = (data) => {
  // Used by MoveFolder component
  formModal.destination.name = data.name;
  formModal.destination.id = data.id;
};

function handlerCloseDropDown() {
  // Closes the main file/folder dropdowns
  // Iterate over the keys of the reactive objects and set values to false
  Object.keys(isDropDowns.value).forEach((key) => {
    isDropDowns.value[key] = false;
  });
  Object.keys(isDropDownsFiles.value).forEach((key) => {
    isDropDownsFiles.value[key] = false;
  });
}

function setDesDashboard() {
  // Sets destination for move/copy to root
  formModal.destination.name = "داشبورد";
  formModal.destination.id = "";
}

function resetAction() {
  // Resets modal states, forms, errors etc.
  isModalMove.value = false;
  isModalCopy.value = false;
  isModalRename.value = false;
  isModalArchive.value = false;
  isModalDelete.value = false;
  isModalDescription.value = false;
  isModalSort.value = false;
  isModalDepChange.value = false;
  isOpenDepartments.value = false; // Close department dropdown too

  // currFolder/currFile are updated by watcher, no need to reset here if form is reset
  errorMessage.value = "";
  errors.value = []; // Clear errors

  form.reset();
  formModal.reset();
  handlerCloseDropDown(); // Ensure dropdowns are closed
}

function subCheckBox() {
  // Toggles checkbox selection mode
  if (!isCheckBoxing.value) {
    form.reset(); // Clear single selection when entering checkbox mode
  }
  handlerCloseDropDown();
  isCheckBoxing.value = !isCheckBoxing.value;
}

// Map toolbar actions to functions
const itemFunctions = {
  checkBox: subCheckBox,
  copy: openModalsCopy,
  move: openModalsMove,
  download: subDownload,
  archive: openModalArchive,
  delete: openModalDelete,
  refresh: subRefresh,
  sort: openModalSort,
  filter: openSearchAdvance,
};

function getItemInToolbar(item) {
  // Executes function based on toolbar click
  const itemFunction = itemFunctions[item];
  if (itemFunction) {
    // Ensure items are selected for actions that require it
    const requiresSelection = ["copy", "move", "download", "archive", "delete"];
    if (
      requiresSelection.includes(item) &&
      form.folders.length === 0 &&
      form.files.length === 0
    ) {
      setAlerts(true, "info", "لطفا ابتدا یک یا چند آیتم را انتخاب کنید.");
      return;
    }
    itemFunction();
  }
}

// Executes action based on dropdown menu click
function choiceFunctionSubmit(slug, link, doc, type) {
  handlerCloseDropDown(); // Close dropdown after action selected

  // Actions map
  const actions = {
    folder: {
      open: () => subOpenFolder(slug, link, doc),
      move: openModalsMove,
      copy: openModalsCopy,
      rename: () => openModalRename(doc, type),
      download: subDownload,
      archive: openModalArchive,
      delete: openModalDelete,
    },
    file: {
      open: () => subOpenFiles(slug, link, doc),
      move: openModalsMove,
      copy: openModalsCopy,
      rename: () => openModalRename(doc, type),
      depChange: () => openDepChange(doc), // Pass doc to identify the file
      download: subDownload,
      archive: openModalArchive,
      delete: openModalDelete,
      description: () => openModalDescription(doc), // Pass doc
    },
  };

  // Ensure the item is selected before performing action (important for right-click context)
  setFormRightClick(doc?.id, type);

  if (type in actions && slug in actions[type]) {
    actions[type][slug]();
  }
}

// Opens the Department Change modal
const openDepChange = (doc = null) => {
  // Ensure setCurrItems runs based on current form selection
  // If called from dropdown ('doc' is provided), ensure that doc is selected
  if (doc) {
    setFormRightClick(doc.id, "file");
  }
  setCurrItems(); // Update currFile/currFolder based on potentially updated form state

  if (currFile.value.length === 1 && currFolder.value.length === 0) {
    const fileToEdit = currFile.value[0];
    // Populate form.departments with OBJECTS from the file's departments
    form.departments = fileToEdit.departments
      ? [...fileToEdit.departments]
      : [];
    isModalDepChange.value = true;
  } else {
    setAlerts(
      true,
      "error",
      "برای ویرایش واحد سازمانی، لطفا دقیقا یک فایل را انتخاب کنید.",
    );
  }
};

function openModalSort() {
  isModalSort.value = true;
}

// Opens the Description modal
function openModalDescription(doc = null) {
  // If called from dropdown ('doc' is provided), ensure that doc is selected
  if (doc) {
    setFormRightClick(doc.id, "file");
  }
  setCurrItems(); // Update currFile/currFolder based on potentially updated form state

  if (currFile.value.length === 1 && currFolder.value.length === 0) {
    form.description = currFile.value[0]?.description || ""; // Set description from the selected file
    isModalDescription.value = true;
  } else {
    setAlerts(
      true,
      "error",
      "برای افزودن توضیحات، لطفا دقیقا یک فایل را انتخاب کنید.",
    );
  }
}

// Updates currFolder/currFile refs based on form selection IDs
function setCurrItems() {
  currFolder.value = props.folders.filter((folder) =>
    form.folders.includes(folder.id),
  );
  currFile.value = props.files.filter((file) => form.files.includes(file.id));
}

// Opens Archive modal
function openModalArchive() {
  setCurrItems(); // Ensure current items are up-to-date
  if (currFolder.value.length > 0 || currFile.value.length > 0) {
    isModalArchive.value = true;
  } else {
    setAlerts(true, "info", "لطفا آیتمی برای آرشیو انتخاب کنید.");
  }
}

// Opens Delete modal
function openModalDelete() {
  setCurrItems(); // Ensure current items are up-to-date
  if (currFolder.value.length > 0 || currFile.value.length > 0) {
    isModalDelete.value = true;
  } else {
    setAlerts(true, "info", "لطفا آیتمی برای حذف انتخاب کنید.");
  }
}

// Opens Rename modal
function openModalRename(doc = null, type = null) {
  // If called from dropdown ('doc' is provided), ensure that doc is selected
  if (doc && type) {
    setFormRightClick(doc.id, type);
  }
  setCurrItems(); // Update currFile/currFolder based on potentially updated form state

  // Check if exactly one item (folder OR file) is selected
  const totalSelected = currFolder.value.length + currFile.value.length;
  if (totalSelected === 1) {
    if (currFolder.value.length === 1) {
      form.folderName = currFolder.value[0]?.name || "";
      baseNameFile.value = ""; // Clear file fields
      extensionFile.value = "";
    } else {
      // Must be a file
      splitFileNameForRename(currFile.value[0]?.name || "");
      form.folderName = ""; // Clear folder field
    }
    isModalRename.value = true;
  } else {
    setAlerts(
      true,
      "error",
      "برای تغییر نام، لطفا دقیقا یک آیتم را انتخاب کنید.",
    );
  }
}

// Splits filename into base and extension for rename modal
function splitFileNameForRename(fileName) {
  if (!fileName) {
    baseNameFile.value = "";
    extensionFile.value = "";
    return;
  }
  const lastDotIndex = fileName.lastIndexOf(".");
  if (lastDotIndex > 0 && lastDotIndex < fileName.length - 1) {
    // Ensure dot is not first or last char
    extensionFile.value = fileName.substring(lastDotIndex + 1);
    baseNameFile.value = fileName.substring(0, lastDotIndex);
  } else {
    baseNameFile.value = fileName; // No extension found or dot is at start/end
    extensionFile.value = "";
  }
}

// Merges base name and extension back into form.fileName
function mergeFileNameForRename() {
  if (currFile.value.length === 1) {
    // Only merge if renaming a file
    form.fileName = `${baseNameFile.value}${extensionFile.value ? "." + extensionFile.value : ""}`;
  }
  // form.folderName is directly bound
}

// Computed property for searching folders based on textSearch
const searchFolder = computed(() => {
  const items = sortFoldersItem.value; // Use sorted list
  if (!textSearch.value) return items;
  const searchTerm = textSearch.value.toLowerCase().trim();
  if (!searchTerm) return items;
  return items.filter((item) => item.name.toLowerCase().includes(searchTerm));
});

// Computed property for searching files based on textSearch
const searchFiles = computed(() => {
  const items = sortFilesItem.value; // Use sorted list
  if (!textSearch.value) return items;
  const searchTerm = textSearch.value.toLowerCase().trim();
  if (!searchTerm) return items;
  return items.filter((item) => item.name.toLowerCase().includes(searchTerm));
});

// Computed property for sorting folders
const sortFoldersItem = computed(() => {
  const foldersCopy = [...props.folders]; // Work on a copy
  if (sortedItems.value === "string") {
    return foldersCopy.sort((a, b) => {
      // Use sort for in-place sort on copy
      return a.name.localeCompare(b.name, "fa"); // Use 'fa' locale for consistency
    });
  }
  // Assuming default order from props is date-based or intended
  return foldersCopy;
});

// Computed property for sorting files
const sortFilesItem = computed(() => {
  const filesCopy = [...props.files]; // Work on a copy
  if (sortedItems.value === "string") {
    return filesCopy.sort((a, b) => {
      // Use sort for in-place sort on copy
      return a.name.localeCompare(b.name, "fa"); // Use 'fa' locale
    });
  }
  return filesCopy;
});

// Refreshes the current view (dashboard or folder)
function subRefresh() {
  const pathSegments = url.value.split("/");
  const lastSlug = pathSegments[pathSegments.length - 1]; // Get last part of URL path

  const isDashboard =
    lastSlug === "dashboard" || url.value.endsWith("/dashboard"); // Check if it's the dashboard route

  const routeName = isDashboard
    ? "web.user.dashboard.index"
    : "web.user.dashboard.folder.show";
  const routeParams = isDashboard ? {} : { folderId: lastSlug };

  Inertia.visit(route(routeName, routeParams), {
    method: "get",
    replace: true,
    preserveState: true, // Preserve local component state like search term
    onStart: () => {
      isRefreshIcon.value = true;
    },
    onSuccess: () => {
      resetAction();
    }, // Reset forms/modals after data reloads
    onFinish: () => {
      isRefreshIcon.value = false;
    },
    onError: () => {
      setAlerts(true, "error", "خطا در بارگذاری مجدد اطلاعات.");
      isRefreshIcon.value = false;
    },
  });
}

// Submits archive request
function subArchive() {
  form.post(route("web.user.dashboard.archive-action"), {
    replace: true,
    preserveScroll: true,
    onSuccess: () => {
      resetAction();
      setAlerts(true, "success", "آیتم‌های انتخابی با موفقیت آرشیو شدند.");
    },
    onError: (e) => {
      errors.value = Object.values(e).flat();
      setAlerts(true, "error", "خطا در آرشیو.", errors.value);
    },
  });
}

// Submits delete request
function subDelete() {
  form.post(route("web.user.dashboard.trash-action"), {
    replace: true,
    preserveScroll: true,
    onSuccess: () => {
      resetAction();
      setAlerts(true, "success", "آیتم‌های انتخابی با موفقیت حذف شدند.");
    },
    onError: (e) => {
      errors.value = Object.values(e).flat();
      setAlerts(true, "error", "خطا در حذف.", errors.value);
    },
  });
}

// Submits rename request
function subRename() {
  if (currFile.value.length === 1) {
    mergeFileNameForRename();
  } else if (currFolder.value.length === 1) {
    // folderName is already bound
  } else {
    // Should not happen due to modal open logic, but good to check
    setAlerts(true, "error", "خطای داخلی: آیتم نامعتبر برای تغییر نام.");
    return;
  }

  const isFolderRename = currFolder.value.length === 1;
  const routeItem = isFolderRename
    ? "web.user.dashboard.folder.rename"
    : "web.user.dashboard.file.rename";
  const idParamKey = isFolderRename ? "folderId" : "fileId";
  const itemSlug = isFolderRename
    ? currFolder.value[0]?.slug
    : currFile.value[0]?.slug;

  if (!itemSlug) {
    setAlerts(true, "error", "خطای داخلی: شناسه آیتم یافت نشد.");
    return;
  }

  form.post(route(routeItem, { [idParamKey]: itemSlug }), {
    replace: true,
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      resetAction();
      setAlerts(true, "success", "تغییر نام با موفقیت انجام شد.");
    },
    onError: (e) => {
      errors.value = Object.values(e).flat();
      // Keep modal open on error by not calling resetAction()
      setAlerts(true, "error", "خطا در تغییر نام.", errors.value);
    },
  });
}

// Navigates into a folder
function subOpenFolder(slug, routeItem, doc) {
  Inertia.visit(route(routeItem, { folderId: doc?.slug }), {
    method: "get",
    preserveState: false, // Usually want to reset state when navigating to a new folder
    // onSuccess: () => { resetAction(); } // resetAction is likely not needed here as component state will reset anyway
  });
}

// Opens Move modal
function openModalsMove() {
  setCurrItems(); // Ensure current items are up-to-date
  if (currFolder.value.length > 0 || currFile.value.length > 0) {
    handlerCloseDropDown();
    errorMessage.value = ""; // Clear previous errors
    isModalMove.value = true;
  } else {
    setAlerts(true, "info", "لطفا آیتمی برای انتقال انتخاب کنید.");
  }
}

// Opens Copy modal
function openModalsCopy() {
  setCurrItems(); // Ensure current items are up-to-date
  if (currFolder.value.length > 0 || currFile.value.length > 0) {
    handlerCloseDropDown();
    errorMessage.value = ""; // Clear previous errors
    isModalCopy.value = true;
  } else {
    setAlerts(true, "info", "لطفا آیتمی برای کپی انتخاب کنید.");
  }
}

// Submits Move request
function subMove() {
  form.destinationFolder = formModal.destination?.id;
  errorMessage.value = ""; // Clear previous errors

  // Basic check: Prevent moving a folder into itself
  if (form.folders.includes(form.destinationFolder)) {
    errorMessage.value = "نمی‌توانید یک پوشه را درون خودش منتقل کنید.";
    return;
  }
  // Note: Preventing move into child requires tree traversal, not implemented here.

  form.post(route("web.user.dashboard.move"), {
    replace: true, // Use replace to avoid adding intermediate states to history
    preserveScroll: true,
    preserveState: true, // Preserve state on error
    onSuccess: () => {
      resetAction();
      setAlerts(true, "success", "انتقال با موفقیت انجام شد.");
    },
    onError: (e) => {
      errors.value = Object.values(e).flat();
      errorMessage.value = errors.value.join(" "); // Show first error in modal space
      setAlerts(true, "error", "خطا در انتقال.", errors.value);
      // Keep modal open
    },
  });
}

// Submits Copy request
function subCopy() {
  form.destinationFolder = formModal.destination?.id;
  errorMessage.value = ""; // Clear previous errors

  form.post(route("web.user.dashboard.copy"), {
    replace: true,
    preserveScroll: true,
    preserveState: true, // Preserve state on error
    onSuccess: () => {
      resetAction();
      setAlerts(true, "success", "کپی با موفقیت انجام شد.");
    },
    onError: (e) => {
      errors.value = Object.values(e).flat();
      errorMessage.value = errors.value.join(" "); // Show first error in modal space
      setAlerts(true, "error", "خطا در کپی.", errors.value);
      // Keep modal open
    },
  });
}

// Initiates download process (creates zip first)
function subDownload() {
  setCurrItems(); // Ensure current items are up-to-date
  if (form.folders.length === 0 && form.files.length === 0) {
    setAlerts(true, "info", "لطفا آیتمی برای دانلود انتخاب کنید.");
    return;
  }

  form.post(route("web.user.dashboard.create-zip"), {
    replace: true,
    preserveScroll: true,
    onSuccess: (page) => {
      // Use zipFileInfo from the SUCCESS response props
      const updatedZipInfo = page.props.zipFileInfo;
      if (updatedZipInfo && updatedZipInfo.downloadUrl) {
        DownloadFileZip(updatedZipInfo); // Pass the fresh data
        // Don't reset action immediately, let user see success/initiate download
        // resetAction(); // Maybe reset after a short delay?
      } else {
        setAlerts(true, "error", "خطا در آماده سازی فایل زیپ برای دانلود.");
      }
    },
    onError: (e) => {
      errors.value = Object.values(e).flat();
      setAlerts(true, "error", "خطا در ایجاد فایل زیپ.", errors.value);
    },
  });
}

// Triggers the actual file download link click
function DownloadFileZip(zipInfo) {
  if (!zipInfo || !zipInfo.downloadUrl) {
    console.error("DownloadFileZip called with invalid zipInfo:", zipInfo);
    setAlerts(true, "error", "URL دانلود نامعتبر است.");
    return;
  }
  const link = document.createElement("a");
  link.href = zipInfo.downloadUrl;
  link.target = "_blank";
  link.download = zipInfo.zipFileName || "download.zip"; // Provide default name

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Navigates to advanced search page
function openSearchAdvance() {
  Inertia.visit(route("web.user.dashboard.search-form"), {
    method: "get",
  });
}

// Handles single item selection when not in checkbox mode (called by item click)
function funSelectItem(id, type) {
  if (!isCheckBoxing.value) {
    form.reset(); // Clear previous selection
    setForm(id, type); // Select only the clicked item
  }
  // If isCheckBoxing is true, the v-model on the input handles selection toggling
}

// Updates the form's folders/files array based on selection
function setForm(id, type) {
  const formKey = type === "folder" ? "folders" : "files";
  const targetArray = form[formKey];

  // This function is now primarily for single-select logic via funSelectItem
  // and ensuring right-clicked item is selected via setFormRightClick.
  // The v-model handles checkbox mode toggling.
  if (!isCheckBoxing.value) {
    // In single select mode, ensure only this item is in the correct array
    form.reset();
    if (type === "folder") form.folders = [id];
    else form.files = [id];
  } else {
    // Checkbox mode is handled by v-model on the input directly.
    // This function might be called by right-click in checkbox mode
    // via setFormRightClick, which ensures the clicked item is added
    // if not already present (see setFormRightClick logic).
    const index = targetArray.indexOf(id);
    if (index === -1) {
      targetArray.push(id); // Add if not present (for right-click scenario)
    }
    // Do not remove here, v-model handles removal when checkbox is unchecked.
  }
}

// Ensures the right-clicked item is part of the selection
function setFormRightClick(id, type) {
  const formKey = type === "folder" ? "folders" : "files";

  if (isCheckBoxing.value) {
    // In checkbox mode, if right-clicked item is NOT selected, add it to selection.
    // Do not clear other selections.
    if (!form[formKey].includes(id)) {
      form[formKey].push(id);
    }
  } else {
    // In single-select mode, clear everything and select only the right-clicked item.
    form.reset();
    form[formKey].push(id);
  }
  setCurrItems(); // Update current items after modifying form state
}

// Opens the dropdown menu for an item
function openDropDown(id, type) {
  handlerCloseDropDown(); // Close other dropdowns

  // Ensure the right-clicked item is selected correctly based on mode
  setFormRightClick(id, type);

  // Toggle visibility of the specific dropdown
  if (type === "folder") {
    // Vue.set(isDropDowns.value, id, !isDropDowns.value[id]); // Use Vue.set or direct assignment for objects
    isDropDowns.value[id] = !isDropDowns.value[id];
  } else {
    // Vue.set(isDropDownsFiles.value, id, !isDropDownsFiles.value[id]);
    isDropDownsFiles.value[id] = !isDropDownsFiles.value[id];
  }
}

// Helper to show alerts
function setAlerts(isAlert, status, data = "", dataList = []) {
  AlertOption.status = status;
  AlertOption.data = data;
  AlertOption.dataList = Array.isArray(dataList)
    ? dataList
    : dataList
      ? [dataList]
      : []; // Ensure dataList is array
  AlertOption.isAlert = isAlert;

  // Auto-hide alerts after a delay (optional)
  if (isAlert) {
    setTimeout(() => {
      AlertOption.isAlert = false;
    }, 5000); // Hide after 5 seconds
  }
}

// Limits description length (redundant if using maxlength attribute)
function characterLimit() {
  if (form.description && form.description.length > 200) {
    form.description = form.description.slice(0, 200);
  }
}

// Saves the file description
function saveDescription() {
  // Ensure exactly one file is selected (currFile updated by watcher or setCurrItems)
  if (currFile.value.length !== 1) {
    setAlerts(
      true,
      "error",
      "برای ثبت توضیحات، لطفا دقیقا یک فایل را انتخاب کنید.",
    );
    return;
  }

  const routeItem = "web.user.dashboard.file.add-description";
  form.post(route(routeItem, { fileId: currFile.value[0]?.slug }), {
    replace: true,
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      setAlerts(true, "success", "توضیحات مورد نظر با موفقیت ثبت شد.");
      resetAction();
    },
    onError: (e) => {
      errors.value = Object.values(e).flat();
      setAlerts(true, "error", "خطا در ثبت توضیحات.", errors.value);
      // Keep modal open
    },
  });
}

// Saves modified departments for a file
const saveDepartments = () => {
  // Ensure exactly one file is selected
  if (currFile.value.length !== 1) {
    setAlerts(
      true,
      "error",
      "برای ویرایش واحد سازمانی، لطفا دقیقا یک فایل را انتخاب کنید.",
    );
    return;
  }

  const routeName = "web.user.dashboard.file.modify-departments";
  // Map department OBJECTS in form.departments back to IDs for submission
  const departmentIds = form.departments.map((dep) => dep.id);

  // Use a temporary form or manually specify data to send only IDs
  Inertia.post(
    route(routeName, { fileId: currFile.value[0]?.id }),
    { departments: departmentIds }, // Send only IDs
    {
      replace: true,
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        setAlerts(
          true,
          "success",
          "واحد های سازمانی با موفقیت به روزرسانی شدند.",
        );
        resetAction();
      },
      onError: (e) => {
        errors.value = Object.values(e).flat();
        setAlerts(
          true,
          "error",
          "خطا در به روزرسانی واحد های سازمانی.",
          errors.value,
        );
        // Keep modal open. Note: form.departments still holds objects.
        // If reload is needed on error, adjust preserveState or manually refetch.
      },
    },
  );
};

// Explicitly closes file/folder dropdowns (now combined into handleGlobalClick)
function closeDropdown() {
  handlerCloseDropDown(); // Use the unified handler
}

// Returns appropriate background/text color classes based on file type
function setBgFiles(type) {
  // Using more standard Tailwind color classes
  switch (type) {
    case "word":
      return "bg-blue-100 text-blue-700";
    case "pdf":
      return "bg-red-100 text-red-700";
    case "image":
      return "bg-purple-100 text-purple-700";
    case "voice":
      return "bg-orange-100 text-orange-700";
    case "video":
      return "bg-indigo-100 text-indigo-700";
    case "spreadsheet":
      return "bg-green-100 text-green-700";
    case "powerpoint":
      return "bg-pink-100 text-pink-700";
    case "archive":
      return "bg-gray-200 text-gray-700";
    default:
      return "bg-gray-100 text-gray-600"; // Default fallback
  }
}

// Handles opening files (either navigation or external viewer)
function subOpenFiles(slug, routeItem, doc) {
  if (doc?.status === "STATUS_WAITING_FOR_MANUAL_PROCESS") {
    setAlerts(true, "info", "فایل در حال پردازش است، لطفا منتظر بمانید...");
    return;
  }

  const officeViewable = ["spreadsheet", "powerpoint"]; // Removed 'archive'

  if (officeViewable.includes(doc?.type)) {
    const rawFileUrl = route("web.user.dashboard.file.serve.raw", {
      fileId: doc?.slug,
    });
    const externalViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(rawFileUrl)}`;
    window.open(externalViewerUrl, "_blank");
  } else if (doc?.type === "archive") {
    // Archives should be downloaded
    setFormRightClick(doc?.id, "file"); // Ensure the file is selected in the form
    subDownload(); // Trigger the download process
  } else {
    Inertia.visit(route(routeItem, { fileId: doc?.slug }), {
      method: "get",
      preserveState: true, // Preserve state if navigating within the app's context
    });
  }
}
</script>
