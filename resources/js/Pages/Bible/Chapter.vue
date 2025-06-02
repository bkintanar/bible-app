<template>
  <BibleLayout
    :current-book="currentBook"
    :chapter-number="chapterNumber"
    :books="books"
    :testament-books="testamentBooks"
    :current-translation="currentTranslation"
    :available-translations="availableTranslations"
    :capabilities="capabilities"
  >
    <div class="space-y-4 px-4 py-4">
      <!-- Floating Navigation Buttons -->
      <a
        v-if="chapterNumber > 1"
        :href="`/${currentBook.osis_id}/${chapterNumber - 1}`"
        class="touch-friendly fixed left-0 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 shadow-lg z-40 no-underline"
        style="width: 30px; min-width: 30px; height: 64px; top: 50%; margin-top: -32px; display: flex; align-items: center; justify-content: center;"
        title="Previous Chapter">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
        </svg>
      </a>

      <a
        v-if="chapterNumber < chapters.length"
        :href="`/${currentBook.osis_id}/${chapterNumber + 1}`"
        class="touch-friendly fixed right-0 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 shadow-lg z-40 no-underline"
        style="width: 30px; min-width: 30px; height: 64px; top: 50%; margin-top: -32px; display: flex; align-items: center; justify-content: center;"
        title="Next Chapter">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
        </svg>
      </a>

      <!-- Chapter Content -->
      <div class="p-4 sm:p-8">
        <div ref="verseContainer" class="bible-text prose prose-lg dark:prose-invert max-w-none" :style="{ fontSize: currentFontSize + 'rem' }">
          <!-- Paragraph Format -->
          <template v-if="formatStyle === 'paragraph' && paragraphs">
            <!-- Chapter titles -->
            <div v-if="paragraphs.length > 0 && paragraphs[0].verses && paragraphs[0].verses[0] && paragraphs[0].verses[0].chapter_titles" class="mb-6">
              <div v-html="paragraphs[0].verses[0].chapter_titles"></div>
            </div>

            <div v-for="(paragraph, index) in paragraphs" :key="index">
              <!-- Line break -->
              <div v-if="paragraph.type === 'line_break'" class="my-6">
                <div v-html="paragraph.combined_text"></div>
              </div>

              <!-- Normal paragraph -->
              <div v-else class="mb-6">
                <p class="mb-4 leading-relaxed" :id="`paragraph-${paragraph.verses[0]?.verse_number || ''}`">
                  <template v-for="(group, groupIndex) in paragraph.verse_groups" :key="groupIndex">
                    <span
                      v-if="group.highlighted"
                      class="bg-yellow-100 dark:bg-yellow-900 px-2 py-1 rounded"
                      style="display: inline;">
                      <span
                        v-for="(verse, verseIndex) in group.verses"
                        :key="verseIndex"
                        class="paragraph-verse-hoverable verse-content">
                        <span
                          class="verse-number text-blue-600 dark:text-blue-400"
                          :id="`verse-${verse.verse_number}`">
                          {{ verse.verse_number }}
                        </span>
                        <span class="text-gray-900 dark:text-gray-100" v-html="verse.text"></span>
                      </span>
                    </span>
                    <template v-else>
                      <span
                        v-for="(verse, verseIndex) in group.verses"
                        :key="verseIndex"
                        class="paragraph-verse-hoverable verse-content">
                        <span
                          class="verse-number text-blue-600 dark:text-blue-400"
                          :id="`verse-${verse.verse_number}`">
                          {{ verse.verse_number }}
                        </span>
                        <span class="text-gray-800 dark:text-gray-200" v-html="verse.text"></span>
                      </span>
                    </template>
                  </template>
                </p>
              </div>
            </div>
          </template>

          <!-- Verse Format -->
          <template v-else>
            <!-- Chapter titles -->
            <div v-if="verses.length > 0 && verses[0].chapter_titles" class="mb-6">
              <div v-html="verses[0].chapter_titles"></div>
            </div>

            <p
              v-for="verse in verses"
              :key="verse.verse_number"
              class="mb-4 leading-relaxed verse-hoverable verse-content"
              :class="verse.highlight_class"
              :id="`verse-${verse.verse_number}`">
              <span class="verse-number text-blue-600 dark:text-blue-400">
                {{ verse.verse_number }}
              </span>
              <span class="text-gray-800 dark:text-gray-200" v-html="verse.text"></span>
            </p>
          </template>
        </div>
      </div>

      <!-- Font Size Controls -->
      <div class="fixed bottom-4 right-4 flex flex-col gap-2 sm:hidden">
        <button
          @click="increaseFontSize"
          :disabled="currentFontSize >= maxFontSize"
          class="touch-friendly bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 p-2 rounded-full shadow-lg border border-gray-200 dark:border-gray-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"></path>
          </svg>
        </button>
        <button
          @click="decreaseFontSize"
          :disabled="currentFontSize <= minFontSize"
          class="touch-friendly bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 p-2 rounded-full shadow-lg border border-gray-200 dark:border-gray-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"></path>
          </svg>
        </button>
      </div>
    </div>
  </BibleLayout>
</template>

<script>
import { ref, onMounted, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import BibleLayout from '@/Layouts/BibleLayout.vue'

export default {
  components: {
    BibleLayout,
  },
  props: {
    currentBook: Object,
    chapterNumber: Number,
    verses: Array,
    paragraphs: Array,
    formatStyle: String,
    chapters: Array,
    books: Array,
    testamentBooks: Object,
    currentTranslation: Object,
    availableTranslations: Array,
    capabilities: Object,
  },
  setup(props) {
    const isNavigating = ref(false)
    const verseContainer = ref(null)

    // Font size controls
    const currentFontSize = ref(parseFloat(localStorage.getItem('bibleFontSize')) || 1.125)
    const minFontSize = 0.875
    const maxFontSize = 1.875
    const fontSizeStep = 0.125

    const navigateToChapter = (bookOsisId, chapterNumber) => {
      console.log('Navigation clicked:', bookOsisId, chapterNumber)

      // Simple navigation without loading states for now
      router.visit(`/${bookOsisId}/${chapterNumber}`, {
        preserveScroll: false,
      })
    }

    const increaseFontSize = () => {
      if (currentFontSize.value < maxFontSize) {
        currentFontSize.value = Math.min(maxFontSize, currentFontSize.value + fontSizeStep)
        localStorage.setItem('bibleFontSize', currentFontSize.value)
      }
    }

    const decreaseFontSize = () => {
      if (currentFontSize.value > minFontSize) {
        currentFontSize.value = Math.max(minFontSize, currentFontSize.value - fontSizeStep)
        localStorage.setItem('bibleFontSize', currentFontSize.value)
      }
    }

    const preventVerseNumberOrphaning = () => {
      if (!verseContainer.value) return

      const verseContainers = verseContainer.value.querySelectorAll('.verse-content')

      verseContainers.forEach(container => {
        const verseNumber = container.querySelector('.verse-number')
        if (!verseNumber) return

        const textSpan = verseNumber.nextElementSibling
        if (!textSpan) return

        const textContent = textSpan.textContent || textSpan.innerText
        const firstWordMatch = textContent.match(/^(\s*)(\S+)(\s*)/)

        if (firstWordMatch) {
          const [fullMatch, leadingSpace, firstWord, trailingSpace] = firstWordMatch

          const wrapper = document.createElement('span')
          wrapper.style.whiteSpace = 'nowrap'
          wrapper.style.display = 'inline'

          wrapper.appendChild(verseNumber.cloneNode(true))

          const firstWordSpan = document.createElement('span')
          firstWordSpan.textContent = leadingSpace + firstWord + trailingSpace
          firstWordSpan.className = textSpan.className
          if (textSpan.style.cssText) {
            firstWordSpan.style.cssText = textSpan.style.cssText
          }
          wrapper.appendChild(firstWordSpan)

          const walker = document.createTreeWalker(
            textSpan,
            NodeFilter.SHOW_TEXT,
            null,
            false
          )

          let firstTextNode = walker.nextNode()
          if (firstTextNode) {
            const nodeText = firstTextNode.textContent
            if (nodeText.startsWith(fullMatch)) {
              firstTextNode.textContent = nodeText.substring(fullMatch.length)
            }
          }

          container.insertBefore(wrapper, textSpan)
          verseNumber.remove()
        }
      })
    }

    onMounted(() => {
      nextTick(() => {
        preventVerseNumberOrphaning()
      })
    })

    return {
      isNavigating,
      verseContainer,
      currentFontSize,
      minFontSize,
      maxFontSize,
      navigateToChapter,
      increaseFontSize,
      decreaseFontSize,
    }
  },
}
</script>
