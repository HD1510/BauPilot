import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';

export type NoteItem = {
    id: number;
    body: string;
    author: string | null;
    created_at: string | null;
};

/**
 * Projekt-Notizen (M7): kurze Einträge von Büro und Baustelle.
 */
export function NotesSection({
    projectId,
    notes,
}: {
    projectId: number;
    notes: NoteItem[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        body: '',
    });

    return (
        <div className="grid max-w-xl gap-3">
            <Heading
                variant="small"
                title="Notizen"
                description="Kurze Einträge zum Projekt — z. B. von der Baustelle"
            />
            {notes.map((note) => (
                <div
                    key={note.id}
                    className="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <p className="whitespace-pre-line">{note.body}</p>
                    <div className="mt-1 flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            {note.author}
                            {note.created_at &&
                                ` · ${formatDate(note.created_at)}`}
                        </span>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Notiz löschen"
                            onClick={() =>
                                router.delete(`/notes/${note.id}`, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                </div>
            ))}
            {notes.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Noch keine Notizen.
                </p>
            )}
            <form
                className="grid gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    post(`/projects/${projectId}/notes`, {
                        preserveScroll: true,
                        onSuccess: () => reset(),
                    });
                }}
            >
                <Textarea
                    value={data.body}
                    onChange={(event) => setData('body', event.target.value)}
                    placeholder="Neue Notiz…"
                    rows={2}
                    required
                />
                <InputError message={errors.body} />
                <Button
                    type="submit"
                    className="w-fit"
                    disabled={processing || !data.body.trim()}
                >
                    Notiz speichern
                </Button>
            </form>
        </div>
    );
}
