import { Head, Link, router } from '@inertiajs/react';
import { CircleCheck, RotateCcw } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';

type TaskRow = {
    id: number;
    project_id: number;
    project: string | null;
    kind: string;
    kind_label: string;
    title: string;
    description: string | null;
    due_on: string | null;
    assignee: string | null;
    done_at: string | null;
};

type Filters = { kind: string; done: boolean; mine: boolean };

export default function TasksIndex({
    tasks,
    filters,
}: {
    tasks: TaskRow[];
    filters: Filters;
}) {
    const applyFilters = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        router.get(
            '/tasks',
            {
                ...(merged.kind ? { kind: merged.kind } : {}),
                ...(merged.done ? { done: 1 } : {}),
                ...(merged.mine ? { mine: 1 } : {}),
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Aufgaben" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Aufgaben & Mängel"
                    description="Alle offenen Aufgaben der Firma — angelegt wird am jeweiligen Projekt"
                />

                <div className="flex flex-wrap items-center gap-4">
                    <Select
                        value={filters.kind || 'all'}
                        onValueChange={(value) =>
                            applyFilters({ kind: value === 'all' ? '' : value })
                        }
                    >
                        <SelectTrigger className="w-36" aria-label="Art">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Alle Arten</SelectItem>
                            <SelectItem value="task">Aufgaben</SelectItem>
                            <SelectItem value="defect">Mängel</SelectItem>
                        </SelectContent>
                    </Select>
                    <Label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={filters.mine}
                            onCheckedChange={(checked) =>
                                applyFilters({ mine: checked === true })
                            }
                        />
                        Nur meine
                    </Label>
                    <Label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={filters.done}
                            onCheckedChange={(checked) =>
                                applyFilters({ done: checked === true })
                            }
                        />
                        Erledigte anzeigen
                    </Label>
                </div>

                <div className="grid max-w-3xl gap-2">
                    {tasks.map((task) => (
                        <div
                            key={task.id}
                            className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                        >
                            <Badge
                                variant={
                                    task.kind === 'defect'
                                        ? 'destructive'
                                        : 'secondary'
                                }
                            >
                                {task.kind_label}
                            </Badge>
                            <div className="flex-1">
                                <span
                                    className={
                                        task.done_at
                                            ? 'font-medium text-muted-foreground line-through'
                                            : 'font-medium'
                                    }
                                >
                                    {task.title}
                                </span>
                                <div className="text-muted-foreground">
                                    <Link
                                        href={`/projects/${task.project_id}`}
                                        className="underline-offset-2 hover:underline"
                                    >
                                        {task.project}
                                    </Link>
                                    {task.assignee && ` · ${task.assignee}`}
                                </div>
                            </div>
                            {task.due_on && (
                                <Badge
                                    variant={
                                        !task.done_at &&
                                        task.due_on <
                                            new Date()
                                                .toISOString()
                                                .slice(0, 10)
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                >
                                    {formatDate(task.due_on)}
                                </Badge>
                            )}
                            {task.done_at ? (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`${task.title} wieder öffnen`}
                                    onClick={() =>
                                        router.post(
                                            `/tasks/${task.id}/reopen`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <RotateCcw className="size-4" />
                                </Button>
                            ) : (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`${task.title} erledigen`}
                                    onClick={() =>
                                        router.post(
                                            `/tasks/${task.id}/complete`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <CircleCheck className="size-4" />
                                </Button>
                            )}
                        </div>
                    ))}
                    {tasks.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Nichts offen. 🎉
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

TasksIndex.layout = {
    breadcrumbs: [{ title: 'Aufgaben', href: '/tasks' }],
};
