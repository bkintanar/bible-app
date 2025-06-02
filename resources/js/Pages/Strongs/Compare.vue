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
          <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">🔍 Compare Strong's Numbers</h1>
          <p class="text-gray-600 dark:text-gray-400">Compare multiple Strong's numbers side by side</p>
        </div>

        <!-- Comparisons -->
        <div v-if="Object.keys(comparisons).length > 0" class="space-y-8">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div
              v-for="(comparison, strongsNumber) in comparisons"
              :key="strongsNumber"
              class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6"
            >
              <div class="mb-4">
                <Link
                  :href="`/strongs/${strongsNumber}`"
                  class="text-xl font-bold text-blue-600 dark:text-blue-400 hover:underline"
                >
                  {{ strongsNumber }}
                </Link>
                <div class="flex items-center gap-2 mt-2">
                  <span v-if="comparison.language" class="px-2 py-1 text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full">
                    {{ comparison.language }}
                  </span>
                  <span v-if="comparison.part_of_speech" class="px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full">
                    {{ comparison.part_of_speech }}
                  </span>
                </div>
              </div>

              <div class="space-y-3">
                <div v-if="comparison.original_word">
                  <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Original Word</h3>
                  <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ comparison.original_word }}
                    <span v-if="comparison.transliteration" class="text-sm font-normal text-gray-600 dark:text-gray-400 ml-2">
                      ({{ comparison.transliteration }})
                    </span>
                  </div>
                </div>

                <div v-if="comparison.short_definition">
                  <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Definition</h3>
                  <div class="text-gray-700 dark:text-gray-300">{{ comparison.short_definition }}</div>
                </div>

                <div v-if="comparison.occurrence_count">
                  <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Usage</h3>
                  <div class="text-sm text-gray-600 dark:text-gray-400">
                    {{ comparison.occurrence_count }} occurrences in Scripture
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Thematic Groups -->
          <div v-if="thematicGroups.length > 0" class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">🏷️ Thematic Connections</h2>

            <div class="space-y-4">
              <div
                v-for="group in thematicGroups"
                :key="group.theme"
                class="border border-gray-200 dark:border-gray-600 rounded-lg p-4"
              >
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ group.theme }}</h3>
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ group.description }}</div>
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="strongsNum in group.strongs_numbers"
                    :key="strongsNum"
                    class="px-2 py-1 text-xs font-medium bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded"
                  >
                    {{ strongsNum }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12">
          <div class="text-gray-500 dark:text-gray-400">
            <svg class="mx-auto h-16 w-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <h3 class="text-xl font-medium mb-2">No Numbers to Compare</h3>
            <p class="max-w-md mx-auto">
              Provide Strong's numbers in the URL to compare them. Example: /strongs/compare?numbers=G2316,H430
            </p>
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
    comparisons: Object,
    thematicGroups: Array,
    strongsNumbers: Array,
  },
}
</script>
