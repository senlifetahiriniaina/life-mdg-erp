import { usePage } from '@inertiajs/vue3'

/**
 * Composable for formatting values consistently across all module pages.
 */
export function useFormatters() {
    const page = usePage()

    function formatCurrency(amount, currency = 'USD') {
        if (amount === null || amount === undefined) return '—'
        return new Intl.NumberFormat(page.props.locale ?? 'en', {
            style: 'currency',
            currency: currency ?? 'USD',
            minimumFractionDigits: 2,
        }).format(amount)
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—'
        return new Intl.DateTimeFormat(page.props.locale ?? 'en', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        }).format(new Date(dateStr))
    }

    function formatDatetime(dateStr) {
        if (!dateStr) return '—'
        return new Intl.DateTimeFormat(page.props.locale ?? 'en', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(dateStr))
    }

    function formatNumber(value, decimals = 0) {
        if (value === null || value === undefined) return '—'
        return new Intl.NumberFormat(page.props.locale ?? 'en', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(value)
    }

    function formatPercent(value) {
        if (value === null || value === undefined) return '—'
        return `${Number(value).toFixed(1)}%`
    }

    return { formatCurrency, formatDate, formatDatetime, formatNumber, formatPercent }
}
