<template>
  <BibleLayout
    :current-book="null"
    :chapter-number="null"
    :books="books"
    :testament-books="testamentBooks"
    :current-translation="currentTranslation"
    :available-translations="availableTranslations"
    :capabilities="capabilities"
    :hide-header-search="true"
  >
    <div class="flex flex-col h-full">
      <!-- Fixed Search Form -->
      <div class="flex-shrink-0 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 p-4 sm:p-8">
        <SearchForm
          :initial-value="searchTerm || ''"
          variant="search-page"
          placeholder="Search for verses, words, or references..."
        />
      </div>

      <!-- Scrollable Content Area -->
      <div class="flex-1 overflow-y-auto p-4 sm:p-8">
        <!-- Search Results -->
        <div v-if="searchTerm">
          <!-- Search Info -->
          <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
              Search Results for "{{ searchTerm }}"
            </h2>
            <div class="text-sm text-gray-600 dark:text-gray-400">
              <span v-if="searchInfo && searchInfo.count > 0">
                Found {{ searchInfo.count }} result{{ searchInfo.count !== 1 ? 's' : '' }}
                <span v-if="hasMoreResults">(showing first {{ limit }})</span>
                in {{ searchInfo.time_ms }}ms
              </span>
              <span v-else-if="searchInfo">
                No results found in {{ searchInfo.time_ms }}ms
              </span>
            </div>
          </div>

          <!-- Results -->
          <div v-if="results && results.length > 0" class="space-y-4">
            <div
              v-for="(result, index) in results"
              :key="result.osis_id"
              :id="`result-${index}`"
              class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-shadow"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="text-sm font-medium text-blue-600 dark:text-blue-400 mb-2">
                    <a :href="`/${result.book_osis_id}/${result.chapter}`" class="hover:underline">
                      {{ result.reference }}
                    </a>
                  </div>
                  <div class="bible-text text-gray-900 dark:text-gray-100" v-html="result.text"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- No Results -->
          <div v-else class="text-center py-12">
            <div class="text-gray-500 dark:text-gray-400">
              <svg class="mx-auto h-12 w-12 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <h3 class="text-lg font-medium mb-2">No results found</h3>
              <p>Try searching with different keywords or check your spelling.</p>
            </div>
          </div>

          <!-- More Results Link -->
          <div v-if="hasMoreResults" class="mt-8 text-center">
            <a
              :href="`/search?q=${encodeURIComponent(searchTerm)}&limit=${limit + 50}&scroll_to=${limit - 10}`"
              class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors inline-block"
            >
              Load More Results
            </a>
          </div>
        </div>

        <!-- Initial State (No Search) -->
        <div v-else class="text-center py-12">
          <div class="text-gray-500 dark:text-gray-400">
            <svg class="mx-auto h-16 w-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <h3 class="text-xl font-medium mb-2">Search the Bible</h3>
            <p class="max-w-md mx-auto">
              Search for verses, words, phrases, or Bible references. Try searching for "love", "John 3:16", or "peace".
            </p>
          </div>
        </div>
      </div>
    </div>
  </BibleLayout>
</template>

<script>
import BibleLayout from '@/Layouts/BibleLayout.vue'
import SearchForm from '@/Components/SearchForm.vue'
import { Link } from '@inertiajs/vue3'
import { onMounted, nextTick } from 'vue'

export default {
  name: 'SearchPage',
  components: {
    BibleLayout,
    SearchForm,
    Link,
  },
  props: {
    searchTerm: String,
    searchType: String,
    results: Array,
    searchInfo: Object,
    groupedResults: Array,
    totalFound: Number,
    hasMoreResults: Boolean,
    limit: Number,
    scrollTo: Number,
    books: Array,
    testamentBooks: Object,
    currentTranslation: Object,
    availableTranslations: Array,
    capabilities: Object,
  },
  setup(props) {
    onMounted(() => {
      if (props.scrollTo !== undefined && props.scrollTo !== null) {
        // Use multiple delays to ensure DOM is fully rendered in NativePHP
        setTimeout(() => {
          const targetIndex = Math.max(0, props.scrollTo)
          const targetElement = document.getElementById(`result-${targetIndex}`)
          if (targetElement) {
            try {
              // Try scrollIntoView first (more compatible with webviews)
              targetElement.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
              })

              // Add offset for fixed header after scrollIntoView
              setTimeout(() => {
                const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop
                window.scrollTo({
                  top: currentScrollTop - 120,
                  behavior: 'smooth'
                })
              }, 100)
            } catch (error) {
              // Fallback: Direct scroll without smooth behavior
              const offsetTop = targetElement.offsetTop - 120
              window.scrollTo(0, offsetTop)
            }
          }
        }, 500) // Longer delay for NativePHP
      }
    })

    return {}
  }
}
</script>
