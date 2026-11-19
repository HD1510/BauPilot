import { Link2, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Eingabeformular erst auf Klick: Die Seite zeigt zuerst, was da ist —
 * das Formular zum Hinzufügen klappt nur auf, wenn man es braucht, und
 * bleibt dann offen, damit mehrere Einträge hintereinander gehen.
 */
export function RevealForm({
    label,
    icon = 'plus',
    children,
}: {
    label: string;
    icon?: 'plus' | 'link';
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const content = useRef<HTMLDivElement>(null);
    const Icon = icon === 'link' ? Link2 : Plus;

    // Nach dem Aufklappen gleich losschreiben können.
    useEffect(() => {
        if (open) {
            content.current
                ?.querySelector<HTMLElement>('input, textarea')
                ?.focus();
        }
    }, [open]);

    if (!open) {
        return (
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="w-fit"
                onClick={() => setOpen(true)}
            >
                <Icon className="size-4" />
                {label}
            </Button>
        );
    }

    return (
        <div ref={content} className="grid gap-2">
            {children}
            <Button
                type="button"
                variant="ghost"
                size="sm"
                className="w-fit text-muted-foreground"
                onClick={() => setOpen(false)}
            >
                Schließen
            </Button>
        </div>
    );
}
