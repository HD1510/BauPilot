import { useRef, useState } from 'react';
import type { DragEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Drag & Drop um einen bestehenden Upload-Bereich: Dateien lassen sich
 * direkt aus dem Explorer hineinziehen — beim Überfahren erscheint eine
 * gestrichelte Ablage-Markierung. Klick-Auswahl bleibt unverändert.
 */
export function FileDropZone({
    onFiles,
    disabled = false,
    className,
    children,
}: {
    onFiles: (files: File[]) => void;
    disabled?: boolean;
    className?: string;
    children: ReactNode;
}) {
    // dragenter/dragleave feuern für jedes Kind-Element — nur wenn die
    // Tiefe wieder 0 ist, hat der Zeiger die Zone wirklich verlassen.
    const depth = useRef(0);
    const [active, setActive] = useState(false);

    const hasFiles = (event: DragEvent) =>
        Array.from(event.dataTransfer.types).includes('Files');

    return (
        <div
            className={cn('relative', className)}
            onDragEnter={(event) => {
                if (disabled || !hasFiles(event)) {
                    return;
                }

                event.preventDefault();
                depth.current += 1;
                setActive(true);
            }}
            onDragOver={(event) => {
                if (disabled || !hasFiles(event)) {
                    return;
                }

                event.preventDefault();
            }}
            onDragLeave={() => {
                if (disabled) {
                    return;
                }

                depth.current = Math.max(0, depth.current - 1);

                if (depth.current === 0) {
                    setActive(false);
                }
            }}
            onDrop={(event) => {
                if (disabled) {
                    return;
                }

                event.preventDefault();
                depth.current = 0;
                setActive(false);

                const files = Array.from(event.dataTransfer.files);

                if (files.length > 0) {
                    onFiles(files);
                }
            }}
        >
            {children}
            {active && (
                <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-lg border-2 border-dashed border-primary bg-background/85">
                    <span className="text-sm font-medium">
                        Datei hier ablegen
                    </span>
                </div>
            )}
        </div>
    );
}

/**
 * Datei(en) in ein natives Datei-Eingabefeld übernehmen, damit der
 * gewohnte Dateiname neben „Choose File" sichtbar wird.
 */
export function assignToInput(
    input: HTMLInputElement | null,
    files: File[],
): void {
    if (input === null) {
        return;
    }

    const transfer = new DataTransfer();

    for (const file of files) {
        transfer.items.add(file);
    }

    input.files = transfer.files;
}
