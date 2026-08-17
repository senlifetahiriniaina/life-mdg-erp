<template>
  <AppLayout>
    <Head :title="$t('helpdesk.portal.title')" />

    <!-- Hero -->
    <div class="portal-hero">
      <h1 class="hero-title">{{ $t('helpdesk.portal.hero_title') }}</h1>
      <p class="hero-subtitle">{{ $t('helpdesk.portal.hero_subtitle') }}</p>
      <div class="hero-search">
        <i class="pi pi-search search-icon" />
        <input
          v-model="searchQuery"
          class="hero-input"
          type="text"
          :placeholder="$t('helpdesk.portal.search_placeholder')"
          @input="onSearch"
        />
      </div>
    </div>

    <!-- Search results -->
    <div v-if="searchResults.length > 0" class="wh-panel" style="margin-bottom:24px">
      <div class="panel-title">{{ $t('helpdesk.portal.search_results') }}</div>
      <div class="article-list">
        <a
          v-for="article in searchResults"
          :key="article.id"
          class="article-row"
          href="#"
          @click.prevent="openArticle(article)"
        >
          <div class="article-title">{{ article.title }}</div>
          <div class="article-meta">{{ article.category?.name }}</div>
        </a>
      </div>
    </div>

    <!-- Category grid -->
    <div class="section-title">{{ $t('helpdesk.portal.browse_categories') }}</div>
    <div class="category-grid">
      <button
        v-for="cat in categories"
        :key="cat.id"
        class="category-card"
        @click="selectedCategory = cat"
        :class="{ 'category-card-active': selectedCategory?.id === cat.id }"
      >
        <i :class="['cat-icon', cat.icon || 'pi pi-folder']" />
        <div class="cat-name">{{ cat.name }}</div>
        <div v-if="cat.description" class="cat-desc">{{ cat.description }}</div>
      </button>
    </div>

    <!-- Featured articles -->
    <div class="section-title" style="margin-top:32px">{{ $t('helpdesk.portal.featured_articles') }}</div>
    <div class="wh-panel">
      <div v-if="featuredArticles.length === 0" class="empty-state">{{ $t('helpdesk.portal.no_articles') }}</div>
      <div class="article-list">
        <a
          v-for="article in featuredArticles"
          :key="article.id"
          class="article-row"
          href="#"
          @click.prevent="openArticle(article)"
        >
          <div>
            <div class="article-title">{{ article.title }}</div>
            <div class="article-excerpt" v-if="article.excerpt">{{ article.excerpt }}</div>
          </div>
          <div class="article-views">
            <i class="pi pi-eye" style="font-size:11px" /> {{ article.view_count }}
          </div>
        </a>
      </div>
    </div>

    <!-- Submit ticket CTA -->
    <div class="ticket-cta">
      <div>
        <div class="cta-title">{{ $t('helpdesk.portal.cta_title') }}</div>
        <div class="cta-sub">{{ $t('helpdesk.portal.cta_subtitle') }}</div>
      </div>
      <a href="/helpdesk/tickets" class="btn btn-primary">
        <i class="pi pi-ticket" style="font-size:13px" />
        {{ $t('helpdesk.portal.submit_ticket') }}
      </a>
    </div>

    <!-- Article modal -->
    <Dialog v-model:visible="showArticle" modal :header="currentArticle?.title" :style="{ width: '720px', maxHeight:'80vh' }">
      <div v-if="currentArticle">
        <div class="article-meta-row">
          <span class="article-category-badge">{{ currentArticle.category?.name }}</span>
          <span class="article-views"><i class="pi pi-eye" style="font-size:11px" /> {{ currentArticle.view_count }}</span>
        </div>
        <div class="article-content">{{ currentArticle.content }}</div>
        <div class="article-feedback">
          <span>{{ $t('helpdesk.portal.was_helpful') }}</span>
          <button class="feedback-btn" :class="{ active: feedbackGiven === 'yes' }" @click="giveFeedback(true)">
            <i class="pi pi-thumbs-up" /> {{ $t('helpdesk.portal.yes') }}
          </button>
          <button class="feedback-btn" :class="{ active: feedbackGiven === 'no' }" @click="giveFeedback(false)">
            <i class="pi pi-thumbs-down" /> {{ $t('helpdesk.portal.no') }}
          </button>
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()

const props = defineProps({
  categories:       { type: Array, default: () => [] },
  featuredArticles: { type: Array, default: () => [] },
})

const categories       = ref(props.categories)
const featuredArticles = ref(props.featuredArticles)
const selectedCategory = ref(null)
const searchQuery      = ref('')
const searchResults    = ref([])
const showArticle      = ref(false)
const currentArticle   = ref(null)
const feedbackGiven    = ref(null)

let searchTimer = null

function onSearch() {
  clearTimeout(searchTimer)
  if (searchQuery.value.length < 2) {
    searchResults.value = []
    return
  }
  searchTimer = setTimeout(async () => {
    try {
      const res = await fetch(`/api/v1/helpdesk/kb/portal/articles?search=${encodeURIComponent(searchQuery.value)}`, {
        headers: { Accept: 'application/json' },
      })
      if (res.ok) {
        const data = await res.json()
        searchResults.value = Array.isArray(data) ? data : (data.data ?? [])
      }
    } catch { /* silent */ }
  }, 300)
}

async function openArticle(article) {
  feedbackGiven.value = null
  try {
    const res = await fetch(`/api/v1/helpdesk/kb/portal/articles/${article.slug || article.id}`, {
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      currentArticle.value = await res.json()
      showArticle.value    = true
    }
  } catch { /* silent */ }
}

async function giveFeedback(helpful) {
  if (!currentArticle.value || feedbackGiven.value) return
  feedbackGiven.value = helpful ? 'yes' : 'no'
  await fetch(`/api/v1/helpdesk/kb/portal/articles/${currentArticle.value.id}/helpful`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ helpful }),
  })
}
</script>

<style scoped>
.portal-hero {
  background: linear-gradient(135deg, var(--halo-600, #4f46e5) 0%, var(--halo-800, #3730a3) 100%);
  border-radius: var(--r-xl, 16px);
  padding: 48px 40px;
  text-align: center;
  margin-bottom: 32px;
  color: #fff;
}
.hero-title { font-size: 32px; font-weight: 700; margin: 0 0 8px; font-family: var(--font-display); }
.hero-subtitle { font-size: 16px; opacity: 0.85; margin: 0 0 24px; }
.hero-search { position: relative; max-width: 500px; margin: 0 auto; }
.search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.6); font-size: 16px; }
.hero-input {
  width: 100%; padding: 14px 16px 14px 44px;
  border-radius: var(--r-xl, 16px); border: 2px solid rgba(255,255,255,0.3);
  background: rgba(255,255,255,0.15); color: #fff; font-size: 15px; outline: none;
  backdrop-filter: blur(4px); box-sizing: border-box;
}
.hero-input::placeholder { color: rgba(255,255,255,0.6); }
.hero-input:focus { border-color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.2); }

.section-title { font-size: 17px; font-weight: 600; color: var(--fg-1); margin-bottom: 16px; }

.category-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
  margin-bottom: 24px;
}
.category-card {
  background: var(--bg-canvas);
  border: 1px solid var(--border-subtle);
  border-radius: var(--r-lg);
  padding: 20px 16px;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s;
}
.category-card:hover { border-color: var(--halo-300); background: var(--halo-50); }
.category-card-active { border-color: var(--halo-400); background: var(--halo-50); }
.cat-icon { font-size: 24px; color: var(--halo-500); margin-bottom: 10px; display: block; }
.cat-name { font-size: 14px; font-weight: 600; color: var(--fg-1); margin-bottom: 4px; }
.cat-desc { font-size: 12px; color: var(--fg-3); }

.wh-panel { background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--r-lg); overflow: hidden; }
.panel-title { font-size: 13px; font-weight: 600; color: var(--fg-2); padding: 12px 16px; border-bottom: 1px solid var(--border-subtle); }

.article-list { display: flex; flex-direction: column; }
.article-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 13px 18px; border-bottom: 1px solid var(--border-subtle);
  text-decoration: none; transition: background 0.1s;
}
.article-row:last-child { border-bottom: 0; }
.article-row:hover { background: var(--bg-sunken); }
.article-title { font-size: 14px; font-weight: 500; color: var(--fg-1); }
.article-excerpt { font-size: 12px; color: var(--fg-3); margin-top: 3px; }
.article-meta { font-size: 11px; color: var(--fg-3); }
.article-views { font-size: 11px; color: var(--fg-4); display: flex; align-items: center; gap: 4px; flex-shrink: 0; }

.ticket-cta {
  margin-top: 32px;
  background: var(--bg-canvas);
  border: 1px solid var(--border-subtle);
  border-radius: var(--r-lg);
  padding: 24px 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.cta-title { font-size: 16px; font-weight: 600; color: var(--fg-1); }
.cta-sub   { font-size: 13px; color: var(--fg-3); margin-top: 4px; }

.btn { font-weight: 500; font-size: 13px; padding: 9px 16px; border-radius: var(--r-md); border: 1px solid transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
.btn-primary { background: var(--halo-500); color: #fff; }
.btn-primary:hover { background: var(--halo-700); }

.empty-state { padding: 32px; text-align: center; color: var(--fg-3); font-size: 13px; }

/* Article modal */
.article-meta-row { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.article-category-badge { background: var(--halo-50); color: var(--halo-700); border-radius: var(--r-pill); padding: 3px 10px; font-size: 12px; font-weight: 500; }
.article-content { font-size: 14px; color: var(--fg-1); line-height: 1.7; white-space: pre-wrap; }
.article-feedback { margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--fg-2); }
.feedback-btn { padding: 6px 14px; border-radius: var(--r-md); border: 1px solid var(--border-subtle); background: var(--bg-canvas); cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
.feedback-btn.active { background: var(--halo-50); border-color: var(--halo-300); color: var(--halo-700); }
</style>
