import { router, useForm } from '@inertiajs/react';
import { Camera, FileText, FolderDown, Trash2, Upload } from 'lucide-react';
import { useRef } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatFileSize } from '@/lib/format';
import { saveFileAs } from '@/lib/save-file';

export type DocumentItem = {
    id: number;
    original_name: string;
    category_label: string;
    size: number;
    is_image?: boolean;
};

/**
 * Beleg vom Server holen und lokal ablegen — der Speicherort ist über
 * den „Speichern unter"-Dialog frei wählbar (Fallback: Download-Ordner).
 */
async function saveDocumentLocally(document: DocumentItem): Promise<void> {
    try {
        const response = await fetch(`/documents/${document.id}/download`, {
            credentials: 'same-origin',
        });

        if (!response.ok) {
            toast.error('Die Datei konnte nicht geladen werden.');

            return;
        }

        const outcome = await saveFileAs(
            await response.blob(),
            document.original_name,
        );

        if (outcome === 'saved') {
            toast.success(`Lokal gespeichert: ${document.original_name}`);
        } else if (outcome === 'download') {
            toast.info(
                'Der Browser bietet keinen Speichern-unter-Dialog — die Datei liegt im Download-Ordner.',
            );
        }
    } catch {
        toast.error('Keine Verbindung — bitte erneut versuchen.');
    }
}

const categories = [
    { value: 'invoice', label: 'Rechnung' },
    { value: 'offer', label: 'Angebot' },
    { value: 'plan', label: 'Plan' },
    { value: 'photo', label: 'Foto' },
    { value: 'delivery_note', label: 'Lieferschein' },
    { value: 'other', label: 'Sonstiges' },
];

/**
 * Belege-Bereich: private Anhänge mit Kategorie, Download nur über den
 * autorisierten Server-Endpunkt. Mit photoGallery (Projekt-Hub, M7)
 * erscheinen Bilder als Galerie samt Kamera-Aufnahme fürs Handy.
 */
export function DocumentsSection({
    documentableType,
    documentableId,
    documents,
    canWrite,
    canUpload,
    defaultCategory = 'other',
    photoGallery = false,
}: {
    documentableType: string;
    documentableId: number;
    documents: DocumentItem[];
    canWrite: boolean;
    /** Hochladen darf ggf. auch, wer sonst nicht schreiben darf (Baustelle am Projekt). */
    canUpload?: boolean;
    defaultCategory?: string;
    photoGallery?: boolean;
}) {
    const uploadAllowed = canUpload ?? canWrite;
    const photos = photoGallery
        ? documents.filter((document) => document.is_image)
        : [];
    const files = photoGallery
        ? documents.filter((document) => !document.is_image)
        : documents;

    return (
        <div className="grid max-w-xl gap-3">
            <Heading
                variant="small"
                title={photoGallery ? 'Fotos & Dateien' : 'Dateien'}
                description="Anhänge liegen privat im Dokumentenspeicher"
            />
            {photoGallery && photos.length > 0 && (
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    {photos.map((photo) => (
                        <figure key={photo.id} className="group relative">
                            <a
                                href={`/documents/${photo.id}/preview`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <img
                                    src={`/documents/${photo.id}/preview`}
                                    alt={photo.original_name}
                                    loading="lazy"
                                    className="aspect-square w-full rounded-lg border border-sidebar-border/70 object-cover dark:border-sidebar-border"
                                />
                            </a>
                            {canWrite && (
                                <button
                                    type="button"
                                    aria-label={`${photo.original_name} löschen`}
                                    className="absolute top-1 right-1 hidden rounded-md bg-black/60 p-1 text-white group-hover:block"
                                    onClick={() =>
                                        router.delete(
                                            `/documents/${photo.id}`,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            )}
                        </figure>
                    ))}
                </div>
            )}
            {files.map((document) => (
                <div
                    key={document.id}
                    className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <FileText className="size-4 shrink-0 text-muted-foreground" />
                    <div className="flex-1">
                        <a
                            href={`/documents/${document.id}/download`}
                            className="font-medium underline-offset-2 hover:underline"
                        >
                            {document.original_name}
                        </a>
                        <div className="text-sm text-muted-foreground">
                            {document.category_label} ·{' '}
                            {formatFileSize(document.size)}
                        </div>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`${document.original_name} lokal speichern (Speicherort wählen)`}
                        title="Lokal speichern (Speicherort wählen)"
                        onClick={() => void saveDocumentLocally(document)}
                    >
                        <FolderDown className="size-4" />
                    </Button>
                    {canWrite && (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`${document.original_name} löschen`}
                            onClick={() =>
                                router.delete(`/documents/${document.id}`, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    )}
                </div>
            ))}
            {documents.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Noch keine Dateien.
                </p>
            )}
            {uploadAllowed && (
                <UploadForm
                    documentableType={documentableType}
                    documentableId={documentableId}
                    defaultCategory={defaultCategory}
                    withCamera={photoGallery}
                />
            )}
        </div>
    );
}

function UploadForm({
    documentableType,
    documentableId,
    defaultCategory,
    withCamera,
}: {
    documentableType: string;
    documentableId: number;
    defaultCategory: string;
    withCamera: boolean;
}) {
    const cameraInput = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm<{
        documentable_type: string;
        documentable_id: number;
        category: string;
        file: File | null;
        client_uuid: string | null;
    }>({
        documentable_type: documentableType,
        documentable_id: documentableId,
        category: defaultCategory,
        file: null,
        client_uuid: null,
    });

    // Kamera-Aufnahme (M7): Foto vom Handy landet in Sekunden am Projekt —
    // ein Tipper, aufnehmen, fertig. client_uuid macht Wiederholungen
    // (Funkloch, Doppel-Tipper) unschädlich.
    const submitCameraShot = (file: File) => {
        router.post(
            '/documents',
            {
                documentable_type: documentableType,
                documentable_id: documentableId,
                category: 'photo',
                file,
                client_uuid: crypto.randomUUID(),
            },
            { preserveScroll: true, forceFormData: true },
        );
    };

    return (
        <form
            className="grid gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post('/documents', {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => reset('file'),
                });
            }}
        >
            <div className="flex items-end gap-3">
                <div className="grid flex-1 gap-2">
                    <Label
                        htmlFor={`file-${documentableType}-${documentableId}`}
                    >
                        Datei hochladen (max. 25 MB)
                    </Label>
                    <Input
                        id={`file-${documentableType}-${documentableId}`}
                        type="file"
                        onChange={(event) =>
                            setData('file', event.target.files?.[0] ?? null)
                        }
                        required
                    />
                    <InputError message={errors.file ?? errors.category} />
                </div>
                <Select
                    value={data.category}
                    onValueChange={(value) => setData('category', value)}
                >
                    <SelectTrigger className="w-40" aria-label="Kategorie">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {categories.map((category) => (
                            <SelectItem
                                key={category.value}
                                value={category.value}
                            >
                                {category.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button type="submit" disabled={processing || !data.file}>
                    <Upload className="size-4" />
                    Hochladen
                </Button>
            </div>
            {withCamera && (
                <>
                    <input
                        ref={cameraInput}
                        type="file"
                        accept="image/*"
                        capture="environment"
                        className="hidden"
                        aria-hidden
                        tabIndex={-1}
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            if (file) {
                                submitCameraShot(file);
                            }

                            event.target.value = '';
                        }}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        className="w-fit"
                        disabled={processing}
                        onClick={() => cameraInput.current?.click()}
                    >
                        <Camera className="size-4" />
                        Foto aufnehmen
                    </Button>
                </>
            )}
        </form>
    );
}
