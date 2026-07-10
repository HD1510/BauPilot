import { router, useForm } from '@inertiajs/react';
import { CircleCheck, RotateCcw, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { formatDate } from '@/lib/format';

export type TaskItem = {
    id: number;
    kind: string;
    kind_label: string;
    title: string;
    description: string | null;
    due_on: string | null;
    assignee: string | null;
    done_at: string | null;
};

/**
 * Aufgaben & Mängel am Projekt (M7): anlegen, erledigen, wieder öffnen.
 * Erledigen ist zustands-idempotent — die zugehörige Frist verschwindet
 * von selbst aus der Fristen-Ansicht.
 */
export function TasksSection({
    projectId,
    tasks,
    members,
}: {
    projectId: number;
    tasks: TaskItem[];
    members: { id: number; name: string }[];
}) {
    const open = tasks.filter((task) => !task.done_at);
    const done = tasks.filter((task) => task.done_at);

    return (
        <div className="grid max-w-xl gap-3">
            <Heading
                variant="small"
                title="Aufgaben & Mängel"
                description="Erinnerungen laufen über die Fristen-Ansicht und den täglichen Digest"
            />
            {open.map((task) => (
                <TaskRow key={task.id} task={task} />
            ))}
            {open.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Nichts offen — sehr gut.
                </p>
            )}
            {done.length > 0 && (
                <details className="text-sm">
                    <summary className="cursor-pointer text-muted-foreground">
                        {done.length} erledigt
                    </summary>
                    <div className="mt-2 grid gap-2">
                        {done.map((task) => (
                            <TaskRow key={task.id} task={task} />
                        ))}
                    </div>
                </details>
            )}
            <CreateTaskForm projectId={projectId} members={members} />
        </div>
    );
}

function TaskRow({ task }: { task: TaskItem }) {
    const isDefect = task.kind === 'defect';

    return (
        <div className="flex items-start gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
            <div className="flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className={
                            task.done_at
                                ? 'font-medium text-muted-foreground line-through'
                                : 'font-medium'
                        }
                    >
                        {task.title}
                    </span>
                    <Badge variant={isDefect ? 'destructive' : 'secondary'}>
                        {task.kind_label}
                    </Badge>
                </div>
                <div className="text-sm text-muted-foreground">
                    {task.due_on && <>fällig {formatDate(task.due_on)}</>}
                    {task.due_on && task.assignee && ' · '}
                    {task.assignee}
                    {task.description && (
                        <p className="mt-1 whitespace-pre-line">
                            {task.description}
                        </p>
                    )}
                </div>
            </div>
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
            <Button
                variant="ghost"
                size="icon"
                aria-label={`${task.title} löschen`}
                onClick={() =>
                    router.delete(`/tasks/${task.id}`, {
                        preserveScroll: true,
                    })
                }
            >
                <Trash2 className="size-4" />
            </Button>
        </div>
    );
}

function CreateTaskForm({
    projectId,
    members,
}: {
    projectId: number;
    members: { id: number; name: string }[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        kind: string;
        title: string;
        due_on: string;
        assignee_user_id: string;
    }>({
        kind: 'task',
        title: '',
        due_on: '',
        assignee_user_id: '',
    });

    return (
        <form
            className="grid gap-3 rounded-lg border border-dashed border-sidebar-border/70 p-3 dark:border-sidebar-border"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/projects/${projectId}/tasks`, {
                    preserveScroll: true,
                    onSuccess: () => reset('title', 'due_on'),
                });
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor={`task-title-${projectId}`}>
                    Neue Aufgabe / neuer Mangel
                </Label>
                <Input
                    id={`task-title-${projectId}`}
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    placeholder="Was ist zu tun?"
                    required
                />
                <InputError message={errors.title} />
            </div>
            <div className="flex flex-wrap items-end gap-3">
                <Select
                    value={data.kind}
                    onValueChange={(value) => setData('kind', value)}
                >
                    <SelectTrigger className="w-32" aria-label="Art">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="task">Aufgabe</SelectItem>
                        <SelectItem value="defect">Mangel</SelectItem>
                    </SelectContent>
                </Select>
                <div className="grid gap-1">
                    <Label
                        htmlFor={`task-due-${projectId}`}
                        className="text-xs text-muted-foreground"
                    >
                        Fällig am
                    </Label>
                    <Input
                        id={`task-due-${projectId}`}
                        type="date"
                        className="w-40"
                        value={data.due_on}
                        onChange={(event) =>
                            setData('due_on', event.target.value)
                        }
                    />
                </div>
                <Select
                    value={data.assignee_user_id || 'none'}
                    onValueChange={(value) =>
                        setData(
                            'assignee_user_id',
                            value === 'none' ? '' : value,
                        )
                    }
                >
                    <SelectTrigger className="w-44" aria-label="Zuständig">
                        <SelectValue placeholder="Zuständig" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">Niemand zuständig</SelectItem>
                        {members.map((member) => (
                            <SelectItem
                                key={member.id}
                                value={String(member.id)}
                            >
                                {member.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button type="submit" disabled={processing || !data.title}>
                    Anlegen
                </Button>
            </div>
            <InputError message={errors.due_on ?? errors.kind} />
        </form>
    );
}
