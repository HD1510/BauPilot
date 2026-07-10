/**
 * Datei lokal ablegen mit frei wählbarem Speicherort: moderne Browser
 * (Chrome/Edge, auch als installierte PWA) öffnen den „Speichern
 * unter"-Dialog; ohne diese Schnittstelle fällt der Browser auf den
 * normalen Download (Download-Ordner) zurück.
 */

type SaveFilePickerWindow = Window & {
    showSaveFilePicker?: (options: {
        suggestedName?: string;
        types?: { description: string; accept: Record<string, string[]> }[];
    }) => Promise<{
        createWritable: () => Promise<{
            write: (data: Blob) => Promise<void>;
            close: () => Promise<void>;
        }>;
    }>;
};

const FILE_TYPES: Record<string, { description: string; extension: string }> = {
    'application/pdf': { description: 'PDF-Datei', extension: '.pdf' },
    'image/jpeg': { description: 'JPEG-Bild', extension: '.jpg' },
    'image/png': { description: 'PNG-Bild', extension: '.png' },
    'image/webp': { description: 'WebP-Bild', extension: '.webp' },
};

export type SaveResult = 'saved' | 'download' | 'cancelled';

export async function saveFileAs(
    blob: Blob,
    suggestedName: string,
): Promise<SaveResult> {
    const picker = (window as SaveFilePickerWindow).showSaveFilePicker;
    const type = FILE_TYPES[blob.type];

    if (picker) {
        try {
            const handle = await picker.call(window, {
                suggestedName,
                types: type
                    ? [
                          {
                              description: type.description,
                              accept: { [blob.type]: [type.extension] },
                          },
                      ]
                    : undefined,
            });
            const writable = await handle.createWritable();
            await writable.write(blob);
            await writable.close();

            return 'saved';
        } catch (error) {
            if ((error as DOMException).name === 'AbortError') {
                return 'cancelled';
            }
            // Sonst: normaler Download als Rückfallebene.
        }
    }

    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = suggestedName;
    anchor.click();
    URL.revokeObjectURL(url);

    return 'download';
}

/**
 * Sprechender Dateiname aus Rechnungsdaten, z. B.
 * „2026-07-01_Huber-Transporte_RE-2026-0815.pdf".
 */
export function suggestedFileName(
    parts: (string | null | undefined)[],
    fallback: string,
): string {
    const extension = fallback.includes('.')
        ? fallback.slice(fallback.lastIndexOf('.'))
        : '';
    const stem = parts
        .filter((part): part is string => !!part && part.trim() !== '')
        .map((part) =>
            part
                .trim()
                .replace(/[^\wäöüÄÖÜß.\- ]+/g, '')
                .replace(/\s+/g, '-'),
        )
        .filter((part) => part !== '')
        .join('_');

    return stem === '' ? fallback : `${stem}${extension}`;
}
