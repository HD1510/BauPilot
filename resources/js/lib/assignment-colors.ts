/**
 * Farbpalette der Einteilungs-Karten — bewusst helle Töne, die auch
 * gedruckt lesbar bleiben. Ein Eintrag über mehrere Tage trägt überall
 * dieselbe Farbe.
 */
export const CARD_COLORS = [
    'border-l-slate-400 bg-slate-50 dark:bg-slate-900/60',
    'border-l-rose-400 bg-rose-50 dark:bg-rose-950/40',
    'border-l-orange-400 bg-orange-50 dark:bg-orange-950/40',
    'border-l-amber-400 bg-amber-50 dark:bg-amber-950/40',
    'border-l-lime-400 bg-lime-50 dark:bg-lime-950/40',
    'border-l-emerald-400 bg-emerald-50 dark:bg-emerald-950/40',
    'border-l-cyan-400 bg-cyan-50 dark:bg-cyan-950/40',
    'border-l-blue-400 bg-blue-50 dark:bg-blue-950/40',
    'border-l-violet-400 bg-violet-50 dark:bg-violet-950/40',
    'border-l-fuchsia-400 bg-fuchsia-50 dark:bg-fuchsia-950/40',
];

export function cardColor(color: number): string {
    return CARD_COLORS[color % CARD_COLORS.length];
}
