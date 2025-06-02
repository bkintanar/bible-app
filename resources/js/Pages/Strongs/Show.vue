<template>
  <BibleLayout
    :current-book="null"
    :chapter-number="null"
    :books="[]"
    :testament-books="{}"
    :current-translation="null"
    :available-translations="[]"
    :capabilities="{}"
    :hide-header-search="true"
  >
    <div class="p-4 sm:p-8">
      <div class="max-w-6xl mx-auto">
        <!-- Back Link -->
        <div class="mb-6">
          <Link href="/strongs" class="text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Concordance
          </Link>
        </div>

        <!-- Main Content -->
        <div v-if="lexicon" class="space-y-8">
          <!-- Header -->
          <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h1 class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-2">{{ strongsNumber }}</h1>
                <div class="flex items-center gap-3 mb-3">
                  <span v-if="lexicon.language" class="px-3 py-1 text-sm font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full">
                    {{ lexicon.language }}
                  </span>
                  <span v-if="lexicon.part_of_speech" class="px-3 py-1 text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full">
                    {{ lexicon.part_of_speech }}
                  </span>
                </div>
              </div>
              <div v-if="usageStats.total_occurrences" class="text-right">
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ usageStats.total_occurrences }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">occurrences</div>
              </div>
            </div>

            <div class="space-y-4">
              <!-- Original Word -->
              <div v-if="lexicon.original_word">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Original Word</h3>
                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                  {{ lexicon.original_word }}
                  <span v-if="lexicon.transliteration" class="text-lg font-normal text-gray-600 dark:text-gray-400 ml-3">
                    ({{ lexicon.transliteration }})
                  </span>
                </div>
                <div v-if="lexicon.pronunciation" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                  Pronunciation: {{ lexicon.pronunciation }}
                </div>
              </div>

              <!-- Definition -->
              <div v-if="lexicon.short_definition">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Definition</h3>
                <div class="text-gray-700 dark:text-gray-300">{{ lexicon.short_definition }}</div>
              </div>

              <!-- Detailed Definition -->
              <div v-if="lexicon.detailed_definition">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Detailed Definition</h3>
                <div class="prose dark:prose-invert max-w-none" v-html="lexicon.detailed_definition"></div>
              </div>

              <!-- Etymology -->
              <div v-if="lexicon.etymology">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Etymology</h3>
                <div class="text-gray-700 dark:text-gray-300">{{ lexicon.etymology }}</div>
              </div>
            </div>
          </div>

          <!-- Usage Statistics -->
          <div v-if="usageStats.total_occurrences > 0" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">📊 Usage Statistics</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Testament Distribution -->
              <div v-if="Object.keys(usageStats.by_testament).length > 0">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">By Testament</h3>
                <div class="space-y-2">
                  <div v-for="(count, testament) in usageStats.by_testament" :key="testament" class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ testament === 'OT' ? 'Old Testament' : 'New Testament' }}</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ count }}</span>
                  </div>
                </div>
              </div>

              <!-- Top Books -->
              <div v-if="Object.keys(usageStats.by_book).length > 0">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Top Books</h3>
                <div class="space-y-2">
                  <div v-for="(count, book) in Object.fromEntries(Object.entries(usageStats.by_book).slice(0, 5))" :key="book" class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ book }}</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ count }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sample Verses -->
          <div v-if="sampleVerses.length > 0" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">📖 Sample Verses</h2>

            <div class="space-y-4">
              <div v-for="verse in sampleVerses" :key="verse.verse_id" class="border-l-4 border-blue-200 dark:border-blue-800 pl-4">
                <div class="text-sm font-medium text-blue-600 dark:text-blue-400 mb-1">
                  {{ verse.reference }}
                </div>
                <div class="text-gray-700 dark:text-gray-300" v-html="verse.text"></div>
                <div v-if="verse.word_text" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Strong's word: <strong>{{ verse.word_text }}</strong>
                </div>
              </div>
            </div>
          </div>

          <!-- Related Words -->
          <div v-if="relatedWords.length > 0" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">🔗 Related Words</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <Link
                v-for="word in relatedWords"
                :key="word.strongs_number"
                :href="`/strongs/${word.strongs_number}`"
                class="block p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:border-blue-300 dark:hover:border-blue-700 transition-colors"
              >
                <div class="font-medium text-blue-600 dark:text-blue-400">{{ word.strongs_number }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">{{ word.original_word }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ word.short_definition }}</div>
              </Link>
            </div>
          </div>
        </div>

        <!-- Loading/Error State -->
        <div v-else class="text-center py-12">
          <div class="text-gray-500 dark:text-gray-400">
            <h3 class="text-lg font-medium mb-2">Strong's Number Not Found</h3>
            <p>The requested Strong's number could not be found.</p>
          </div>
        </div>
      </div>
    </div>
  </BibleLayout>
</template>

<script>
import BibleLayout from '@/Layouts/BibleLayout.vue'
import { Link } from '@inertiajs/vue3'

export default {
  components: {
    BibleLayout,
    Link,
  },
  props: {
    lexicon: Object,
    usageStats: Object,
    relatedWords: Array,
    morphologyAnalysis: Object,
    sampleVerses: Array,
    strongsNumber: String,
  },
}
</script>
