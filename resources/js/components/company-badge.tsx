import { cn } from '@/lib/utils';

/**
 * Firmen-Plakette mit Kennfarbe: kleines farbiges Quadrat mit Kurzzeichen,
 * damit jederzeit sichtbar ist, in welcher Firma gearbeitet wird.
 */
export function CompanyBadge({
    shortCode,
    color,
    className,
}: {
    shortCode: string;
    color: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'flex aspect-square size-8 shrink-0 items-center justify-center rounded-md text-xs font-bold text-white',
                className,
            )}
            style={{ backgroundColor: color }}
            aria-hidden
        >
            {shortCode}
        </span>
    );
}
