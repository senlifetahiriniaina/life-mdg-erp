<template>
  <div :class="['lazy-image-wrapper', wrapperClass]">
    <!-- Loading skeleton -->
    <div
      v-if="!loaded && showSkeleton"
      :class="['lazy-image-skeleton', skeletonClass]"
      :style="{ aspectRatio: `${aspectWidth} / ${aspectHeight}` }"
    />

    <!-- Main image with lazy loading and WebP support -->
    <picture v-show="loaded">
      <!-- WebP format (next-gen, smaller) -->
      <source
        v-if="webpSrc"
        :srcset="webpSrcset"
        type="image/webp"
      />

      <!-- AVIF format (newest, even smaller) -->
      <source
        v-if="avifSrc"
        :srcset="avifSrcset"
        type="image/avif"
      />

      <!-- Fallback to original format (JPEG/PNG) -->
      <img
        :src="src"
        :srcset="srcset"
        :sizes="sizes"
        :alt="alt"
        :loading="loading"
        :class="imageClass"
        :title="alt"
        @load="onImageLoad"
        @error="onImageError"
      />
    </picture>

    <!-- Error fallback -->
    <div
      v-if="error"
      :class="['lazy-image-error', errorClass]"
      :style="{ aspectRatio: `${aspectWidth} / ${aspectHeight}` }"
    >
      <div class="flex flex-col items-center justify-center h-full gap-2 text-surface-500">
        <i class="pi pi-image text-2xl" />
        <span class="text-sm">{{ errorMessage }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';

interface Props {
  // Required
  src: string;
  alt: string;

  // Optional image variants
  webpSrc?: string;          // WebP format (lighter weight)
  avifSrc?: string;          // AVIF format (newest, smallest)
  srcset?: string;           // Responsive image sources (e.g., "small.jpg 480w, large.jpg 1200w")
  webpSrcset?: string;       // WebP responsive sources
  avifSrcset?: string;       // AVIF responsive sources
  sizes?: string;            // Media query breakpoints for srcset

  // Aspect ratio for skeleton (prevents layout shift)
  aspectWidth?: number;
  aspectHeight?: number;

  // Loading behavior
  loading?: 'lazy' | 'eager';  // Default: 'lazy' for below-fold, 'eager' for above-fold
  showSkeleton?: boolean;       // Show loading skeleton

  // CSS classes
  imageClass?: string;
  wrapperClass?: string;
  skeletonClass?: string;
  errorClass?: string;

  // Error handling
  errorMessage?: string;
}

const props = withDefaults(defineProps<Props>(), {
  loading: 'lazy',
  showSkeleton: true,
  aspectWidth: 16,
  aspectHeight: 9,
  errorMessage: 'Image failed to load',
  imageClass: 'w-full h-auto object-cover',
  wrapperClass: 'relative bg-surface-100 dark:bg-surface-800 overflow-hidden',
  skeletonClass: 'absolute inset-0 bg-gradient-to-r from-surface-100 via-surface-200 to-surface-100 dark:from-surface-800 dark:via-surface-700 dark:to-surface-800 animate-pulse',
  errorClass: 'absolute inset-0 bg-surface-50 dark:bg-surface-900',
});

const loaded = ref(false);
const error = ref(false);

const onImageLoad = () => {
  loaded.value = true;
  error.value = false;
};

const onImageError = () => {
  error.value = true;
  loaded.value = true;
};
</script>

<style scoped>
.lazy-image-wrapper {
  display: block;
  position: relative;
}

.lazy-image-skeleton {
  position: absolute;
  inset: 0;
  z-index: 1;
}

picture {
  display: block;
  width: 100%;
}

img {
  display: block;
  width: 100%;
  height: auto;
}
</style>
