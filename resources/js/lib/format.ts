const eur = new Intl.NumberFormat('de-AT', {
    style: 'currency',
    currency: 'EUR',
});

export function formatEUR(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return eur.format(typeof value === 'string' ? Number(value) : value);
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('de-AT');
}

export function formatFileSize(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} kB`;
}
