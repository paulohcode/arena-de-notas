export function toLocalPath(url) {
    if (!url) {
        return null;
    }

    try {
        const parsed = new URL(String(url), window.location.origin);

        return parsed.pathname + parsed.search + parsed.hash;
    } catch {
        return null;
    }
}

export function csrfToken(fallback) {
    return fallback
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '';
}
