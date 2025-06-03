<template>
  <div class="flex flex-col bg-gray-50 dark:bg-gray-900 ios-safe-container">
    <!-- Fixed Header -->
    <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700 flex-shrink-0 z-50 ios-safe-header">
      <div class="px-4 sm:px-6 w-full max-w-none">
        <!-- Main Navigation Row -->
        <div class="flex items-center h-16 w-full">
          <!-- Book and Chapter Info (Left side with flex-1 to take remaining space) -->
          <div class="flex-1">
            <div v-if="currentBook && chapterNumber" class="flex items-center">
              <button @click="showBookSelector = true"
                      class="touch-friendly px-4 py-3 text-gray-900 dark:text-white font-semibold transition-all duration-200 rounded-lg flex items-center justify-center gap-1 whitespace-nowrap"
                      style="font-family: var(--font-bible); font-size: 1.5rem; letter-spacing: 0.025em;">
                <span>{{ currentBook.short_name }} {{ chapterNumber }}</span>
              </button>
            </div>
            <div v-else-if="currentBook" class="flex items-center">
              <button @click="showBookSelector = true"
                      class="touch-friendly px-4 py-3 text-gray-900 dark:text-white font-semibold transition-all duration-200 rounded-lg flex items-center justify-center gap-1 whitespace-nowrap"
                      style="font-family: var(--font-bible); font-size: 1.5rem; letter-spacing: 0.025em;">
                <span>{{ currentBook.short_name }}</span>
              </button>
            </div>
          </div>

          <!-- Mobile Actions -->
          <div class="flex items-center space-x-1 sm:hidden">
            <!-- Search Toggle (Mobile) -->
            <button v-if="!hideHeaderSearch" @click="showMobileSearch = !showMobileSearch"
                    class="touch-friendly p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
            </button>

            <!-- Dark Mode Toggle (Mobile) -->
            <button @click="toggleDarkMode"
                    class="touch-friendly p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-center">
              <svg v-if="isDarkMode" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
              </svg>
              <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
              </svg>
            </button>

            <!-- Menu Toggle (Mobile) -->
            <button @click="showMobileMenu = !showMobileMenu"
                    class="touch-friendly p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <svg v-if="!showMobileMenu" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Desktop Controls -->
          <div class="hidden sm:flex items-center space-x-4">
            <!-- Translation Selector -->
            <div v-if="availableTranslations && availableTranslations.length > 1" class="relative">
              <div class="relative" x-data="{ open: false }" @click.away="open = false">
                <!-- Simple Dropdown Button -->
                <button @click="open = !open"
                        class="flex items-center justify-between bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors min-w-[80px]">
                  <span class="font-medium">{{ currentTranslation ? currentTranslation.short_name : 'Select' }}</span>
                  <svg class="w-4 h-4 ml-2 transition-transform duration-200"
                       :class="{ 'rotate-180': open }"
                       fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                  </svg>
                </button>

                <!-- Simple Dropdown Menu -->
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute z-50 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 py-1 right-0"
                     style="display: none;">

                  <button v-for="translation in availableTranslations"
                          :key="translation.key"
                          @click="selectedTranslation = translation.key; switchTranslation(); open = false"
                          class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                          :class="currentTranslation && currentTranslation.key === translation.key ? 'bg-blue-600 dark:bg-blue-600 text-white dark:text-white' : 'text-gray-700 dark:text-gray-200'">
                    <div class="flex items-center justify-between">
                      <span class="font-medium">{{ translation.short_name }}</span>
                      <svg v-if="currentTranslation && currentTranslation.key === translation.key"
                           class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                      </svg>
                    </div>
                    <div class="text-xs mt-1" :class="currentTranslation && currentTranslation.key === translation.key ? 'text-blue-100 dark:text-blue-100' : 'text-gray-500 dark:text-gray-400'">{{ translation.name }}</div>
                  </button>
                </div>
              </div>
            </div>
            <div v-else-if="currentTranslation" class="bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ currentTranslation.short_name }}</span>
            </div>

            <!-- Desktop Search Form -->
            <SearchForm
              v-if="!hideHeaderSearch"
              variant="header-desktop"
              :show-button="false"
            />

            <!-- Desktop Dark Mode Toggle -->
            <button @click="toggleDarkMode"
                    class="touch-friendly p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-center">
              <svg v-if="isDarkMode" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
              </svg>
              <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile Search Bar -->
      <div v-show="showMobileSearch && !hideHeaderSearch" class="border-t border-gray-200 dark:border-gray-700 p-4 sm:hidden">
        <SearchForm
          variant="header-mobile"
          placeholder="Search verses, words, or Strong's numbers..."
        />
      </div>

      <!-- Mobile Menu -->
      <div v-show="showMobileMenu" class="border-t border-gray-200 dark:border-gray-700 sm:hidden">
        <div class="px-4 py-3 space-y-3">
          <!-- Translation Selector (Mobile) -->
          <div v-if="availableTranslations && availableTranslations.length > 1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Translation</label>
            <div class="relative">
              <select v-model="selectedTranslation"
                      @change="switchTranslation"
                      class="block w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm">
                <option v-for="translation in availableTranslations"
                        :key="translation.key"
                        :value="translation.key">
                  {{ translation.short_name }} - {{ translation.name }}
                </option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </div>
            </div>
          </div>
          <div v-else-if="currentTranslation">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Translation</label>
            <div class="flex items-center bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-3">
              <div class="w-6 h-4 rounded-sm bg-blue-500 flex items-center justify-center mr-2">
                <span class="text-xs font-bold text-white">
                  {{ currentTranslation.short_name.substring(0, 1) }}
                </span>
              </div>
              <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ currentTranslation.short_name }} - {{ currentTranslation.name }}
              </span>
            </div>
          </div>

          <!-- Navigation Links (Mobile) -->
          <div class="pt-3 border-t border-gray-200 dark:border-gray-600">
            <div class="grid grid-cols-1 gap-3">
              <Link href="/strongs" class="touch-friendly flex items-center justify-center space-x-2 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg p-3 font-medium">
                <span>🔤</span>
                <span>Strong's Concordance</span>
              </Link>
              <Link href="/?fresh=1" class="touch-friendly flex items-center justify-center space-x-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg p-3 font-medium">
                <span>📖</span>
                <span>Browse Bible</span>
              </Link>
              <button @click="clearLastVisited" class="touch-friendly flex items-center justify-center space-x-2 bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 rounded-lg p-3 font-medium w-full">
                <span>🔄</span>
                <span>Start Over</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Scrollable Main Content Container -->
    <div class="flex-1 overflow-y-auto ios-safe-content">
      <slot />
    </div>

    <!-- Book Selector Modal -->
    <BookSelector
      v-if="showBookSelector && currentBook && books"
      :current-book="currentBook"
      :books="books"
      :testament-books="testamentBooks"
      @close="showBookSelector = false"
      @book-selected="handleBookSelected"
    />
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import BookSelector from '@/Components/BookSelector.vue'
import SearchForm from '@/Components/SearchForm.vue'

export default {
  components: {
    Link,
    BookSelector,
    SearchForm,
  },
  props: {
    currentBook: Object,
    chapterNumber: Number,
    books: Array,
    testamentBooks: Object,
    currentTranslation: Object,
    availableTranslations: Array,
    capabilities: Object,
    hideHeaderSearch: {
      type: Boolean,
      default: false,
    },
  },
  setup(props) {
    const showMobileSearch = ref(false)
    const showMobileMenu = ref(false)
    const showBookSelector = ref(false)
    const selectedTranslation = ref(props.currentTranslation?.key || '')
    const isDarkMode = ref(false)

    // Initialize dark mode from localStorage
    onMounted(() => {
      isDarkMode.value = localStorage.getItem('darkMode') === 'true'
      if (isDarkMode.value) {
        document.documentElement.classList.add('dark')
      }
    })

    const toggleDarkMode = () => {
      isDarkMode.value = !isDarkMode.value
      document.documentElement.classList.toggle('dark')
      localStorage.setItem('darkMode', isDarkMode.value)
    }

    const switchTranslation = () => {
      router.post('/switch-translation', {
        translation: selectedTranslation.value,
      })
    }

    const clearLastVisited = () => {
      router.post('/clear-last-visited')
    }

    const handleBookSelected = async ({ bookOsisId, bookName, bookShortName }) => {
      // This method is now unused since BookSelector handles book selection internally
      // and emits chapter-selected directly
    }

    return {
      showMobileSearch,
      showMobileMenu,
      showBookSelector,
      selectedTranslation,
      isDarkMode,
      toggleDarkMode,
      switchTranslation,
      clearLastVisited,
      handleBookSelected,
    }
  },
}
</script>
