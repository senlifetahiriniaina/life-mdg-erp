import { onCLS, onFCP, onINP, onLCP, onTTFB } from 'web-vitals'
import * as Sentry from '@sentry/vue'

export interface WebVitalsMetrics {
  name: string
  value: number
  rating: 'good' | 'needs-improvement' | 'poor'
  delta: number
  id: string
}

/**
 * Phase 4: Web Vitals Monitoring
 *
 * Monitor Core Web Vitals and send to Sentry for performance tracking
 * - FCP (First Contentful Paint): <1.8s (good), <2.5s (needs improvement)
 * - LCP (Largest Contentful Paint): <2.5s (good), <4s (needs improvement)
 * - CLS (Cumulative Layout Shift): <0.1 (good), <0.25 (needs improvement)
 * - INP (Interaction to Next Paint): <200ms (good), <500ms (needs improvement)
 * - TTFB (Time to First Byte): <600ms (good)
 */
export function useWebVitals() {
  const metrics: WebVitalsMetrics[] = []

  // Capture First Contentful Paint
  onFCP((metric) => {
    captureMetric(metric)
  })

  // Capture Largest Contentful Paint
  onLCP((metric) => {
    captureMetric(metric)
  })

  // Capture Cumulative Layout Shift
  onCLS((metric) => {
    captureMetric(metric)
  })

  // Capture Interaction to Next Paint (new vital replacing FID)
  onINP((metric) => {
    captureMetric(metric)
  })

  // Capture Time to First Byte
  onTTFB((metric) => {
    captureMetric(metric)
  })

  function captureMetric(metric: WebVitalsMetrics) {
    metrics.push(metric)

    // Send to Sentry with appropriate severity based on rating
    const severity = metric.rating === 'good' ? 'info' : metric.rating === 'needs-improvement' ? 'warning' : 'error'

    Sentry.captureMessage(`Web Vital: ${metric.name} = ${Math.round(metric.value)}ms (${metric.rating})`, {
      level: severity,
      contexts: {
        vitals: {
          name: metric.name,
          value: metric.value,
          rating: metric.rating,
          delta: metric.delta,
        },
      },
    })
  }

  return {
    metrics,
  }
}
