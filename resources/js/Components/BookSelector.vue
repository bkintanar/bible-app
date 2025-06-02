<template>
  <div class="fixed inset-0 bg-white dark:bg-gray-800 z-50" style="height: 100vh; display: flex; flex-direction: column;">
    <!-- Fixed Header -->
    <div class="flex justify-between items-center p-4 border-b border-gray-200 dark:border-gray-600" style="flex-shrink: 0;">
      <div class="flex items-center space-x-3">
        <button v-if="showingChapters" @click="backToBooks" class="touch-friendly p-1 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
          </svg>
        </button>
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
          {{ showingChapters ? selectedBook?.name : 'Select Book' }}
        </h3>
      </div>
      <button @click="$emit('close')" class="touch-friendly p-1 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>

    <!-- Scrollable Content -->
    <div class="p-3" style="flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch;">
      <div class="space-y-4">
        <!-- Book Selection View -->
        <div v-if="!showingChapters">
          <!-- Old Testament -->
          <div>
            <h4 class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2 flex items-center">
              📜 OLD TESTAMENT <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">({{ testamentBooks.oldTestament.length }})</span>
            </h4>
            <div class="grid gap-2 mb-3" style="grid-template-columns: repeat(3, 1fr);">
              <button
                v-for="book in testamentBooks.oldTestament"
                :key="book.osis_id"
                @click="selectBook(book)"
                :class="[
                  'touch-friendly p-2 text-xs font-semibold transition-all duration-200 text-center h-8 flex items-center justify-center border bg-white dark:bg-gray-700 rounded',
                  book.osis_id === currentBook.osis_id
                    ? 'text-blue-600 dark:text-blue-400 shadow-md border-blue-300 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                    : 'text-gray-800 dark:text-gray-200 shadow-sm hover:shadow-md border-gray-200 dark:border-gray-600 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-gray-50 dark:hover:bg-gray-600'
                ]">
                {{ book.name }}
              </button>
            </div>
          </div>

          <!-- New Testament -->
          <div>
            <h4 class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2 flex items-center">
              ✝️ NEW TESTAMENT <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">({{ testamentBooks.newTestament.length }})</span>
            </h4>
            <div class="grid gap-2" style="grid-template-columns: repeat(3, 1fr);">
              <button
                v-for="book in testamentBooks.newTestament"
                :key="book.osis_id"
                @click="selectBook(book)"
                :class="[
                  'touch-friendly p-2 text-xs font-semibold transition-all duration-200 text-center h-8 flex items-center justify-center border bg-white dark:bg-gray-700 rounded',
                  book.osis_id === currentBook.osis_id
                    ? 'text-blue-600 dark:text-blue-400 shadow-md border-blue-300 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                    : 'text-gray-800 dark:text-gray-200 shadow-sm hover:shadow-md border-gray-200 dark:border-gray-600 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-gray-50 dark:hover:bg-gray-600'
                ]">
                {{ book.name }}
              </button>
            </div>
          </div>
        </div>

        <!-- Chapter Selection View -->
        <div v-else>
          <div>
            <h4 class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3 flex items-center">
              📄 CHAPTERS <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">({{ chapters.length }})</span>
            </h4>
            <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));">
              <a
                v-for="chapter in chapters"
                :key="chapter.chapter_number"
                :href="`/${selectedBook.osis_id}/${chapter.chapter_number}`"
                :class="[
                  'touch-friendly p-3 text-sm font-semibold transition-all duration-200 text-center h-12 flex items-center justify-center border bg-white dark:bg-gray-700 rounded',
                  'text-gray-800 dark:text-gray-200 shadow-sm hover:shadow-md border-gray-200 dark:border-gray-600 hover:border-blue-200 dark:hover:border-blue-800 hover:bg-gray-50 dark:hover:bg-gray-600'
                ]">
                {{ chapter.chapter_number }}
              </a>
            </div>
          </div>

          <!-- Popular Chapters (if any) -->
          <div v-if="popularChapters && popularChapters.length > 0">
            <h4 class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3 flex items-center">
              ⭐ POPULAR CHAPTERS
            </h4>
            <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));">
              <a
                v-for="chapterNum in popularChapters"
                :key="`popular-${chapterNum}`"
                :href="`/${selectedBook.osis_id}/${chapterNum}`"
                :class="[
                  'touch-friendly p-3 text-sm font-semibold transition-all duration-200 text-center h-12 flex items-center justify-center border rounded',
                  'text-blue-600 dark:text-blue-400 shadow-sm hover:shadow-md border-blue-300 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30'
                ]">
                {{ chapterNum }}
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Fixed Footer -->
    <div class="p-4 border-t border-gray-200 dark:border-gray-600" style="flex-shrink: 0;">
      <div v-if="showingChapters" class="grid grid-cols-2 gap-3">
        <button @click="backToBooks"
                class="touch-friendly px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-medium transition-colors text-sm">
          Back
        </button>
        <button @click="$emit('close')"
                class="touch-friendly px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors text-sm">
          Close
        </button>
      </div>
      <button v-else @click="$emit('close')"
              class="touch-friendly px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-medium transition-colors w-full text-sm">
        Close
      </button>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    currentBook: Object,
    books: Array,
    testamentBooks: Object,
  },
  emits: ['close', 'book-selected'],
  data() {
    return {
      showingChapters: false,
      selectedBook: null,
      chapters: [],
      popularChapters: [],
      isLoading: false,
    }
  },
  methods: {
    async selectBook(book) {
      this.selectedBook = book
      this.isLoading = true

      // Get popular chapters for this book
      this.popularChapters = this.getPopularChapters(book.osis_id)

      // Fetch chapters for the selected book
      try {
        const response = await fetch(`/api/${book.osis_id}/chapters`)
        const allChapters = await response.json()
        // Filter out chapters with no verses
        this.chapters = allChapters.filter(chapter => chapter.verse_count > 0)
      } catch (error) {
        console.error('Error fetching chapters:', error)
        this.chapters = []
      }

      this.isLoading = false
      this.showingChapters = true
    },

    backToBooks() {
      this.showingChapters = false
      this.selectedBook = null
      this.chapters = []
      this.popularChapters = []
    },

    getPopularChapters(bookOsisId) {
      const popularChapters = {
        'Ps': [23, 91, 139, 1],
        'Prov': [31, 3, 27, 16],
        'John': [3, 14, 15, 1],
        'Rom': [8, 12, 3, 6],
        '1Cor': [13, 15, 10, 2],
      }
      return popularChapters[bookOsisId] || []
    },
  },
}
</script>
