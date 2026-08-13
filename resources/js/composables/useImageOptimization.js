/**
 * Image optimization utilities for responsive, lazy-loaded, compressed images
 *
 * Usage:
 * const { generateResponsiveImages, getImageQuality, shouldLazyLoad } = useImageOptimization()
 */

/**
 * Generate responsive image srcset for different breakpoints and formats
 * @param {string} basePath - Base image path without extension (e.g., '/images/hero')
 * @param {string[]} breakpoints - Image widths (e.g., [320, 640, 1024, 1920])
 * @returns {Object} Object with src, srcset, sizes for different formats
 */
export function generateResponsiveImages(basePath, breakpoints = [320, 640, 1024, 1920]) {
  const formats = {
    jpeg: 'jpg',
    webp: 'webp',
    avif: 'avif',
  };

  const srcset = {};
  Object.entries(formats).forEach(([format, ext]) => {
    srcset[format] = breakpoints
      .map(w => `${basePath}-${w}w.${ext} ${w}w`)
      .join(', ');
  });

  return {
    src: `${basePath}-1024w.jpg`,  // Fallback
    jpegSrcset: srcset.jpeg,
    webpSrcset: srcset.webp,
    avifSrcset: srcset.avif,
    sizes: '(max-width: 640px) 100vw, (max-width: 1024px) 80vw, 1200px',
  };
}

/**
 * Get optimal image quality/compression level based on viewport and format
 * @param {string} format - Image format ('jpeg', 'webp', 'avif')
 * @param {boolean} isMobile - Whether viewing on mobile
 * @returns {number} Quality level (0-100) for image encoding
 */
export function getImageQuality(format = 'jpeg', isMobile = false) {
  const qualities = {
    jpeg: isMobile ? 70 : 80,    // Mobile: more aggressive compression
    webp: isMobile ? 75 : 85,    // WebP handles lower quality better
    avif: isMobile ? 80 : 90,    // AVIF best quality retention
  };

  return qualities[format] || 80;
}

/**
 * Determine if image should be lazy-loaded based on position
 * @param {Element} element - DOM element to check
 * @param {number} rootMargin - Pixel threshold for loading (default: 50px below fold)
 * @returns {boolean} Whether to lazy load
 */
export function shouldLazyLoad(element, rootMargin = 50) {
  if (!element) return false;

  // Images in hero/header section should load eagerly
  const rect = element.getBoundingClientRect();
  const windowHeight = window.innerHeight || document.documentElement.clientHeight;

  // Load eagerly if above fold
  if (rect.bottom > 0 && rect.top < windowHeight) {
    return false; // Above fold, load eagerly
  }

  // Load lazily if below fold
  return true;
}

/**
 * Get image format support in current browser
 * @returns {Object} Supported formats with boolean flags
 */
export function getBrowserImageSupport() {
  const canvas = document.createElement('canvas');

  return {
    webp: canvas.toDataURL('image/webp').includes('webp'),
    avif: canvas.toDataURL('image/avif').includes('avif'),
    jpeg: true,  // Always supported
    png: true,   // Always supported
  };
}

/**
 * Calculate image srcset for common responsive breakpoints
 * @param {string} imagePath - Base image path
 * @param {number[]} widths - Image widths to generate (default: [320, 640, 1024, 1536, 1920])
 * @returns {Object} srcset configurations for different formats
 */
export function calculateResponsiveSrcset(
  imagePath,
  widths = [320, 640, 1024, 1536, 1920]
) {
  const baseWithoutExt = imagePath.replace(/\.(jpg|jpeg|png|webp|avif)$/i, '');

  return {
    jpg: widths.map(w => `${baseWithoutExt}-${w}w.jpg ${w}w`).join(', '),
    webp: widths.map(w => `${baseWithoutExt}-${w}w.webp ${w}w`).join(', '),
    avif: widths.map(w => `${baseWithoutExt}-${w}w.avif ${w}w`).join(', '),
    sizes: '(max-width: 640px) calc(100vw - 32px), (max-width: 1024px) calc(100vw - 64px), 1200px',
  };
}

/**
 * Estimate download size reduction by using WebP/AVIF
 * @param {number} jpegSize - JPEG file size in bytes
 * @returns {Object} Size estimates for different formats
 */
export function estimateCompressionSavings(jpegSize) {
  return {
    jpeg: {
      bytes: jpegSize,
      kb: (jpegSize / 1024).toFixed(2),
    },
    webp: {
      bytes: Math.round(jpegSize * 0.7),  // ~30% smaller
      kb: (jpegSize * 0.7 / 1024).toFixed(2),
      savings: '30%',
    },
    avif: {
      bytes: Math.round(jpegSize * 0.6),  // ~40% smaller
      kb: (jpegSize * 0.6 / 1024).toFixed(2),
      savings: '40%',
    },
  };
}

/**
 * Get SVG optimization recommendations
 * @returns {Object} SVG best practices
 */
export function getSVGOptimizationTips() {
  return {
    inline: 'Inline SVG for <10KB files to avoid extra HTTP requests',
    external: 'Use external SVG for >10KB or reused icons',
    minify: 'Use SVGO or similar tools to minify SVG files by 30-60%',
    sprites: 'Use SVG sprites for icon libraries (<svg><use /></svg>)',
    styling: 'Remove inline styles, use CSS classes instead',
    compression: 'SVGZ (gzip) compression saves another 20-50%',
  };
}

/**
 * Generate picture element markup for lazy-loaded images
 * @param {Object} config - Image configuration
 * @returns {string} HTML picture element markup
 */
export function generatePictureMarkup(config) {
  const {
    alt,
    src,
    jpegSrcset,
    webpSrcset,
    avifSrcset,
    sizes,
    lazy = true,
    width = null,
    height = null,
  } = config;

  const loading = lazy ? 'lazy' : 'eager';
  const attrs = width && height ? ` width="${width}" height="${height}"` : '';

  return `
    <picture>
      <source srcset="${avifSrcset}" type="image/avif" />
      <source srcset="${webpSrcset}" type="image/webp" />
      <img
        src="${src}"
        srcset="${jpegSrcset}"
        sizes="${sizes}"
        alt="${alt}"
        loading="${loading}"
        decoding="async"${attrs}
      />
    </picture>
  `.trim();
}

/**
 * Calculate optimal image dimensions based on layout
 * @param {number} containerWidth - Container width in CSS pixels
 * @param {number} devicePixelRatio - Device pixel ratio (1, 2, 3)
 * @returns {Object} Recommended image width for best quality without over-fetching
 */
export function calculateOptimalImageWidth(containerWidth, devicePixelRatio = window.devicePixelRatio || 1) {
  // Target 2x resolution for Retina, 1.5x for older devices
  const optimalWidth = Math.ceil(containerWidth * devicePixelRatio);

  // Round to common image widths for CDN/image processing
  const commonWidths = [320, 640, 960, 1280, 1536, 1920, 2560];
  return commonWidths.find(w => w >= optimalWidth) || commonWidths[commonWidths.length - 1];
}

/**
 * Get image loading strategy recommendation
 * @param {number} fileSize - Image file size in bytes
 * @param {string} format - Image format ('jpeg', 'webp', 'avif', 'svg')
 * @returns {Object} Loading recommendations
 */
export function getLoadingStrategy(fileSize, format = 'jpeg') {
  const kb = fileSize / 1024;

  if (format === 'svg') {
    return {
      strategy: kb < 5 ? 'inline' : 'external',
      recommendation: kb < 5 ? 'Inline SVG to avoid HTTP request' : 'Use external SVG file',
    };
  }

  if (kb < 20) {
    return {
      strategy: 'eager',
      recommendation: 'Load eagerly (small file size)',
    };
  }

  if (kb < 100) {
    return {
      strategy: 'lazy-above-fold',
      recommendation: 'Consider eager loading if above fold, lazy otherwise',
    };
  }

  return {
    strategy: 'lazy-below-fold',
    recommendation: 'Lazy load (larger file, likely below fold)',
  };
}

export default {
  generateResponsiveImages,
  getImageQuality,
  shouldLazyLoad,
  getBrowserImageSupport,
  calculateResponsiveSrcset,
  estimateCompressionSavings,
  getSVGOptimizationTips,
  generatePictureMarkup,
  calculateOptimalImageWidth,
  getLoadingStrategy,
};
