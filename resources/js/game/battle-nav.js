/**
 * Navega para a tela de combate. Se a URL for a mesma da sala de espera,
 * força reload — senão o browser fica preso em "aguardando".
 */
export function goToBattle(url) {
    const target = toBattleUrl(url);
    if (! target) {
        return;
    }

    const current = window.location.pathname + window.location.search;
    const targetUrl = new URL(target, window.location.origin);
    const next = targetUrl.pathname + targetUrl.search;

    if (next === current || targetUrl.pathname === window.location.pathname) {
        window.location.replace(next.includes('?') ? `${next}&_=${Date.now()}` : `${next}?_=${Date.now()}`);
        return;
    }

    window.location.href = next;
}

function toBattleUrl(url) {
    if (! url) {
        return null;
    }

    try {
        const parsed = new URL(String(url), window.location.origin);

        return parsed.pathname + parsed.search + parsed.hash;
    } catch {
        return String(url);
    }
}
