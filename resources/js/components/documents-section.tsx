import { router, useForm } from '@inertiajs/react';
import { FileText, Trash2, Upload } from 'lucide-react';
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

export type DocumentItem = {
    id: number;
    original_name: string;
    category_label: string;
    size: number;
};

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
 * autorisierten Server-Endpunkt.
 */
export function DocumentsSection({
    documentableType,
    documentableId,
    documents,
    canWrite,
    defaultCategory = 'other',
}: {
    documentableType: string;
    documentableId: number;
    documents: DocumentItem[];
    canWrite: boolean;
    defaultCategory?: string;
}) {
    return (
        <div className="grid max-w-xl gap-3">
            <Heading
                variant="small"
                title="Dateien"
                description="Anhänge liegen privat im Dokumentenspeicher"
            />
            {documents.map((document) => (
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
            {canWrite && (
                <UploadForm
                    documentableType={documentableType}
                    documentableId={documentableId}
                    defaultCategory={defaultCategory}
                />
            )}
        </div>
    );
}

function UploadForm({
    documentableType,
    documentableId,
    defaultCategory,
}: {
    documentableType: string;
    documentableId: number;
    defaultCategory: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        documentable_type: string;
        documentable_id: number;
        category: string;
        file: File | null;
    }>({
        documentable_type: documentableType,
        documentable_id: documentableId,
        category: defaultCategory,
        file: null,
    });

    return (
        <form
            className="flex items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post('/documents', {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => reset('file'),
                });
            }}
        >
            <div className="grid flex-1 gap-2">
                <Label htmlFor={`file-${documentableType}-${documentableId}`}>
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
                        <SelectItem key={category.value} value={category.value}>
                            {category.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button type="submit" disabled={processing || !data.file}>
                <Upload className="size-4" />
                Hochladen
            </Button>
        </form>
    );
}
