// TODO: Phase 5.2 - Update web-vitals API for v5.x
// Current: web-vitals v5.2.0 uses onCLS, onFCP, onLCP, onFID, onINP instead
// import { getCLS, getFCP, getLCP, getTTFB, getFID } from 'web-vitals';
import * as Sentry from '@sentry/vue';

// Cached metrics to avoid redundant processing
const metricsCache = new Map();

/**
 * Initialize Web Vitals tracking with Sentry integration.
 * Captures Core Web Vitals and sends them to Sentry for real-user monitoring.
 *
 * Metrics captured:
 * - LCP (Largest Contentful Paint): Time until main content is visible
 * - FCP (First Contentful Paint): Time until any content appears
 * - CLS (Cumulative Layout Shift): Visual stability during page load
 * - TTFB (Time to First Byte): Server response time
 * - FID (First Input Delay): Responsiveness to first interaction
 */
export function useWebVitals() {
    const SENTRY_DSN = import.meta.env.VITE_SENTRY_DSN || '';

    const trackMetric = (name, metric) => {
        if (!SENTRY_DSN || metricsCache.has(name)) return;

        metricsCache.set(name, metric);

        // Log metric value based on type
        let message = '';
        let rating = getMetricRating(name, metric.value);

        switch (name) {
            case 'lcp':
                message = `LCP: ${metric.value.toFixed(0)}ms (${rating})`;
                break;
            case 'fcp':
                message = `FCP: ${metric.value.toFixed(0)}ms (${rating})`;
                break;
            case 'cls':
                message = `CLS: ${metric.value.toFixed(3)} (${rating})`;
                break;
            case 'ttfb':
                message = `TTFB: ${metric.value.toFixed(0)}ms (${rating})`;
                break;
            case 'fid':
                message = `FID: ${metric.value.toFixed(0)}ms (${rating})`;
                break;
        }

        // Send to Sentry with appropriate level based on performance
        const level = rating === 'good' ? 'info' : rating === 'poor' ? 'warning' : 'info';
        Sentry.captureMessage(message, level, {
            tags: {
                metric: name,
                rating: rating,
                page: window.location.pathname,
            },
            extra: {
                id: metric.id,
                delta: metric.delta,
                value: metric.value,
                attribution: metric.attribution || {},
            },
        });
    };

    const initializeTracking = () => {
        if (!SENTRY_DSN) return;

        getCLS((metric) => trackMetric('cls', metric));
        getFCP((metric) => trackMetric('fcp', metric));
        getLCP((metric) => trackMetric('lcp', metric));
        getTTFB((metric) => trackMetric('ttfb', metric));

        // FID is deprecated in favor of INP, but keep for compatibility
        if (typeof getFID !== 'undefined') {
            getFID((metric) => trackMetric('fid', metric));
        }
    };

    const getMetrics = () => {
        return Object.fromEntries(metricsCache);
    };

    const clearMetrics = () => {
        metricsCache.clear();
    };

    return {
        initializeTracking,
        getMetrics,
        clearMetrics,
        trackMetric,
    };
}

/**
 * Get performance rating for a metric based on Web Vitals thresholds.
 * Follows Google's Core Web Vitals recommendations.
 */
export function getMetricRating(name, value) {
    const thresholds = {
        lcp: { good: 2500, poor: 4000 },     // milliseconds
        fcp: { good: 1800, poor: 3000 },     // milliseconds
        cls: { good: 0.1, poor: 0.25 },      // unitless
        ttfb: { good: 600, poor: 1800 },     // milliseconds
        fid: { good: 100, poor: 300 },       // milliseconds
    };

    const threshold = thresholds[name];
    if (!threshold) return 'unknown';

    if (value <= threshold.good) return 'good';
    if (value <= threshold.poor) return 'needs-improvement';
    return 'poor';
}

/**
 * Report Web Vitals to custom endpoint (useful for your own RUM solution).
 * Can be used alongside Sentry or as an alternative.
 */
export async function reportWebVitalsToEndpoint(endpoint = '/api/web-vitals') {
    const { getMetrics } = useWebVitals();
    const metrics = getMetrics();

    if (Object.keys(metrics).length === 0) return;

    try {
        await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                metrics,
                page: window.location.pathname,
                timestamp: new Date().toISOString(),
            }),
        });
    } catch (error) {
        console.error('Failed to report Web Vitals:', error);
    }
}
