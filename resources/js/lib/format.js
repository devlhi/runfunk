export function rupiah(value, withPrefix = true) {
    const number = new Intl.NumberFormat('id-ID').format(Number(value ?? 0));

    return withPrefix ? `Rp ${number}` : number;
}

export function statusBadgeClass(status) {
    return {
        pending_payment: 'badge badge--pending',
        waiting_verification: 'badge badge--waiting',
        confirmed: 'badge badge--confirmed',
        rejected: 'badge badge--rejected',
        cancelled: 'badge badge--cancelled',
    }[status] ?? 'badge';
}

export function countdownParts(targetIso, now = Date.now()) {
    const diff = new Date(targetIso).getTime() - now;

    if (diff <= 0) {
        return { done: true, d: '00', h: '00', m: '00', s: '00' };
    }

    const pad = (n) => String(n).padStart(2, '0');

    return {
        done: false,
        d: pad(Math.floor(diff / 86400000)),
        h: pad(Math.floor((diff % 86400000) / 3600000)),
        m: pad(Math.floor((diff % 3600000) / 60000)),
        s: pad(Math.floor((diff % 60000) / 1000)),
    };
}
