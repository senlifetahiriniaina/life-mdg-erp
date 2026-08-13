/**
 * Font optimization utilities for preventing layout shift during font loading
 * Tracks font loading performance and provides font metric overrides
 */

/**
 * Get font metric overrides to prevent Cumulative Layout Shift (CLS)
 * when custom fonts swap in. These values should match your custom font metrics.
 *
 * @param {string} fontName - Font family name
 * @returns {Object} CSS override values for ascent/descent/line-gap
 */
export function getFontMetricOverrides(fontName) {
  const overrides = {
    'Inter Tight': {
      ascentOverride: '90%',
      descentOverride: '22%',
      lineGapOverride: '0%',
      description: 'Inter Tight body font metrics',
    },
    'Geist': {
      ascentOverride: '90%',
      descentOverride: '22%',
      lineGapOverride: '0%',
      description: 'Geist display font metrics',
    },
    'JetBrains Mono': {
      ascentOverride: '87%',
      descentOverride: '23%',
      lineGapOverride: '0%',
      description: 'JetBrains Mono monospace metrics',
    },
  };

  return overrides[fontName] || null;
}

/**
 * Generate CSS @font-face rules with font-display and metric overrides
 *
 * @param {Object} config - Font configuration
 * @returns {string} CSS @font-face rule
 */
export function generateFontFaceCSS(config) {
  const {
    fontFamily,
    src,
    fontWeight = '400',
    fontStyle = 'normal',
    fontDisplay = 'swap',
    ascentOverride,
    descentOverride,
    lineGapOverride,
  } = config;

  let css = `
@font-face {
  font-family: '${fontFamily}';
  src: url('${src}') format('woff2');
  font-weight: ${fontWeight};
  font-style: ${fontStyle};
  font-display: ${fontDisplay};`;

  if (ascentOverride) css += `\n  ascent-override: ${ascentOverride};`;
  if (descentOverride) css += `\n  descent-override: ${descentOverride};`;
  if (lineGapOverride) css += `\n  line-gap-override: ${lineGapOverride};`;

  css += '\n}';

  return css;
}

/**
 * Detect when custom fonts are loaded and report timing
 * Useful for monitoring font loading performance
 */
export function trackFontLoading() {
  if (!window.document.fonts) return null;

  const fontMetrics = {
    startTime: performance.now(),
    fontsLoading: [],
    fontsLoaded: [],
  };

  // Listen for font loading completion
  document.fonts.ready.then(() => {
    const endTime = performance.now();
    fontMetrics.loadTime = endTime - fontMetrics.startTime;

    // Report to analytics
    if (window.gtag) {
      gtag('event', 'font_loaded', {
        load_time_ms: Math.round(fontMetrics.loadTime),
      });
    }

    console.log(`Fonts loaded in ${fontMetrics.loadTime.toFixed(0)}ms`);
  });

  return fontMetrics;
}

/**
 * Preload fonts programmatically (fallback for older browsers)
 * Most modern approaches use link rel="preload" in HTML
 *
 * @param {string[]} fontUrls - Array of font file URLs
 */
export function preloadFonts(fontUrls) {
  fontUrls.forEach(url => {
    const link = document.createElement('link');
    link.rel = 'preload';
    link.as = 'font';
    link.href = url;
    link.type = 'font/woff2';
    link.crossOrigin = 'anonymous';
    document.head.appendChild(link);
  });
}

/**
 * Check if a specific font is loaded
 *
 * @param {string} fontName - Font family name to check
 * @returns {Promise<boolean>} Resolves when font is loaded
 */
export async function isFontLoaded(fontName) {
  if (!window.document.fonts) return true; // Fallback for unsupported browsers

  try {
    await document.fonts.load(`16px ${fontName}`);
    return true;
  } catch (error) {
    console.warn(`Font ${fontName} failed to load:`, error);
    return false;
  }
}

/**
 * Wait for all critical fonts to load with timeout
 *
 * @param {string[]} fontNames - Font family names to wait for
 * @param {number} timeout - Maximum wait time in milliseconds (default: 3000)
 * @returns {Promise<Object>} Status of font loading
 */
export async function waitForFonts(fontNames = [], timeout = 3000) {
  if (!window.document.fonts) {
    return { allLoaded: true, timeout: false };
  }

  const startTime = performance.now();

  try {
    const fontPromises = fontNames.map(name =>
      Promise.race([
        document.fonts.load(`16px ${name}`),
        new Promise((_, reject) =>
          setTimeout(() => reject(new Error('Font load timeout')), timeout)
        ),
      ])
    );

    await Promise.allSettled(fontPromises);

    const loadTime = performance.now() - startTime;
    return {
      allLoaded: true,
      loadTime,
      timeout: false,
    };
  } catch (error) {
    const loadTime = performance.now() - startTime;
    return {
      allLoaded: false,
      loadTime,
      timeout: error.message === 'Font load timeout',
      error: error.message,
    };
  }
}

/**
 * Get font loading recommendations based on browser
 *
 * @returns {Object} Browser support and recommendations
 */
export function getFontLoadingRecommendations() {
  const support = {
    fontDisplay: CSS.supports('font-display', 'swap'),
    fontFaceMetrics: CSS.supports('ascent-override', '100%'),
    documentFonts: !!window.document?.fonts,
  };

  let recommendations = [];

  if (!support.fontDisplay) {
    recommendations.push('font-display not supported, use font-display: swap fallback');
  }

  if (!support.fontFaceMetrics) {
    recommendations.push('ascent-override not supported, CLS during font swap may occur');
  }

  if (!support.documentFonts) {
    recommendations.push('Document.fonts API not supported, cannot track font loading');
  }

  return {
    support,
    recommendations,
    allSupported: Object.values(support).every(v => v),
  };
}

/**
 * Calculate estimated font file size savings with compression
 *
 * @param {number} woffSize - WOFF file size in bytes
 * @returns {Object} Size estimates for different formats
 */
export function estimateFontCompressionSavings(woffSize) {
  return {
    woff: {
      bytes: woffSize,
      kb: (woffSize / 1024).toFixed(1),
    },
    woff2: {
      bytes: Math.round(woffSize * 0.6),  // ~40% smaller
      kb: (woffSize * 0.6 / 1024).toFixed(1),
      savings: '40%',
    },
    subset: {
      bytes: Math.round(woffSize * 0.4),  // ~60% smaller
      kb: (woffSize * 0.4 / 1024).toFixed(1),
      savings: '60%',
    },
  };
}

/**
 * Get font weight usage recommendations based on design system
 *
 * @returns {Object} Recommended font weights and their usage
 */
export function getRecommendedFontWeights() {
  return {
    400: {
      name: 'Regular',
      usage: 'Body text, paragraphs, default weight',
      essential: true,
      priority: 1,
    },
    500: {
      name: 'Medium',
      usage: 'Slightly emphasized text (radio buttons, small labels)',
      essential: false,
      priority: 4,
      note: 'Can be removed if not used in design',
    },
    600: {
      name: 'Semibold',
      usage: 'Button labels, figure captions, strong emphasis',
      essential: true,
      priority: 2,
    },
    700: {
      name: 'Bold',
      usage: 'Major headings, h1-h3, strong/bold text',
      essential: true,
      priority: 3,
    },
    800: {
      name: 'Extra Bold',
      usage: 'Very rare, display typography only',
      essential: false,
      priority: 5,
      note: 'Usually not needed unless in brand guidelines',
    },
    900: {
      name: 'Black',
      usage: 'Almost never used in web',
      essential: false,
      priority: 6,
      note: 'Avoid unless specifically designed for',
    },
  };
}

/**
 * Generate HTML preload links for fonts
 *
 * @param {Object[]} fonts - Array of font configs
 * @returns {string} HTML link tags for font preload
 */
export function generateFontPreloadHTML(fonts) {
  return fonts
    .map(font => {
      const { href, rel = 'preload', as = 'font', type = 'font/woff2' } = font;
      return `<link rel="${rel}" href="${href}" as="${as}" type="${type}" crossorigin />`;
    })
    .join('\n');
}

/**
 * Font loading performance audit
 * Check if fonts are optimized in current page
 *
 * @returns {Object} Audit results with recommendations
 */
export async function auditFontPerformance() {
  const audit = {
    timestamp: new Date().toISOString(),
    recommendations: [],
    issues: [],
    score: 100,
  };

  // Check font-display support
  if (!CSS.supports('font-display', 'swap')) {
    audit.issues.push('font-display not supported');
    audit.score -= 20;
  }

  // Check for font metric overrides
  const styles = document.querySelectorAll('style, link[rel="stylesheet"]');
  let hasMetricOverrides = false;

  styles.forEach(style => {
    if (style.textContent?.includes('ascent-override')) {
      hasMetricOverrides = true;
    }
  });

  if (!hasMetricOverrides) {
    audit.recommendations.push('Consider adding ascent-override/descent-override to prevent CLS during font swap');
    audit.score -= 10;
  }

  // Check if fonts are preloaded
  const preloadLinks = document.querySelectorAll('link[rel="preload"][as="font"]');
  if (preloadLinks.length === 0) {
    audit.recommendations.push('Consider preloading critical fonts in HTML head');
    audit.score -= 15;
  }

  // Wait for fonts to load and measure time
  const fontLoadMetrics = await waitForFonts(['Inter Tight', 'Geist'], 3000);

  if (fontLoadMetrics.allLoaded && fontLoadMetrics.loadTime > 2000) {
    audit.recommendations.push(`Fonts loaded slowly (${fontLoadMetrics.loadTime.toFixed(0)}ms). Consider self-hosting.`);
    audit.score -= 10;
  }

  return {
    ...audit,
    fontLoadMetrics,
    score: Math.max(0, audit.score),
  };
}

export default {
  getFontMetricOverrides,
  generateFontFaceCSS,
  trackFontLoading,
  preloadFonts,
  isFontLoaded,
  waitForFonts,
  getFontLoadingRecommendations,
  estimateFontCompressionSavings,
  getRecommendedFontWeights,
  generateFontPreloadHTML,
  auditFontPerformance,
};
