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

        <!-- Header -->
        <div class="mb-8">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">📊 Strong's Statistics</h1>
          <p class="text-gray-600 dark:text-gray-400">Overview of the Strong's Concordance database</p>
        </div>

        <!-- Overview Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-2">{{ stats.total_entries.toLocaleString() }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Entries</div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="text-3xl font-bold text-green-600 dark:text-green-400 mb-2">{{ stats.hebrew_count.toLocaleString() }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Hebrew Words</div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="text-3xl font-bold text-purple-600 dark:text-purple-400 mb-2">{{ stats.greek_count.toLocaleString() }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Greek Words</div>
          </div>

          <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="text-3xl font-bold text-orange-600 dark:text-orange-400 mb-2">{{ stats.aramaic_count.toLocaleString() }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Aramaic Words</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Language Distribution -->
          <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Language Distribution</h2>
            <div class="space-y-3">
              <div v-for="lang in languageDistribution" :key="lang.language" class="flex items-center justify-between">
                <span class="text-gray-700 dark:text-gray-300">{{ lang.language }}</span>
                <div class="flex items-center gap-3">
                  <div class="flex-1 bg-gray-200 dark:bg-gray-600 rounded-full h-2 w-32">
                    <div
                      class="h-2 rounded-full"
                      :class="{
                        'bg-green-500': lang.language === 'Hebrew',
                        'bg-purple-500': lang.language === 'Greek',
                        'bg-orange-500': lang.language === 'Aramaic',
                        'bg-gray-500': !['Hebrew', 'Greek', 'Aramaic'].includes(lang.language)
                      }"
                      :style="{ width: (lang.count / stats.total_entries * 100) + '%' }"
                    ></div>
                  </div>
                  <span class="text-sm font-medium text-gray-900 dark:text-gray-100 w-16 text-right">{{ lang.count.toLocaleString() }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Top Words -->
          <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Most Frequent Words</h2>
            <div class="space-y-3">
              <Link
                v-for="word in topWords.slice(0, 10)"
                :key="word.strongs_number"
                :href="`/strongs/${word.strongs_number}`"
                class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group"
              >
                <div class="flex items-center gap-3">
                  <span class="font-medium text-blue-600 dark:text-blue-400 group-hover:underline">{{ word.strongs_number }}</span>
                  <span class="text-sm text-gray-600 dark:text-gray-400">{{ word.original_word }}</span>
                </div>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ word.occurrence_count.toLocaleString() }}</span>
              </Link>
            </div>
          </div>

          <!-- Relationship Types -->
          <div v-if="stats.relationship_types && stats.relationship_types.length > 0" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Word Relationships</h2>
            <div class="space-y-3">
              <div v-for="rel in stats.relationship_types" :key="rel.type" class="flex items-center justify-between">
                <span class="text-gray-700 dark:text-gray-300 capitalize">{{ rel.type }}</span>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ rel.count.toLocaleString() }}</span>
              </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
              <div class="flex items-center justify-between font-semibold">
                <span class="text-gray-900 dark:text-gray-100">Total Relationships</span>
                <span class="text-gray-900 dark:text-gray-100">{{ stats.total_relationships.toLocaleString() }}</span>
              </div>
            </div>
          </div>

          <!-- Additional Stats -->
          <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Additional Information</h2>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-gray-700 dark:text-gray-300">With Pronunciation</span>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ stats.with_pronunciation.toLocaleString() }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-700 dark:text-gray-300">Coverage</span>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {{ Math.round((stats.with_pronunciation / stats.total_entries) * 100) }}%
                </span>
              </div>
            </div>
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
    stats: Object,
    topWords: Array,
    languageDistribution: Array,
  },
}
</script>
