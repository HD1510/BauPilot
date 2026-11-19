import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Camera,
    Check,
    CircleCheck,
    Clock,
    FileSignature,
    Truck,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cardColor } from '@/lib/assignment-colors';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';

type AssignmentCard = {
    id: number;
    label: string;
    project_id: number | null;
    project: string | null;
    notes: string | null;
    color: number;
    colleagues: string[];
    vehicles: string[];
    hours_recorded: number;
};

type MyTask = {
    id: number;
    title: string;
    kind: string;
    kind_label: string;
    due_on: string | null;
    overdue: boolean;
    project_id: number;
    project: string | null;
};

type Props = {
    today: string;
    employee: { id: number; name: string } | null;
    vacation: { ends_on: string; notes: string | null } | null;
    assignments: AssignmentCard[];
    next: { date: string; assignments: AssignmentCard[] } | null;
    hours: { today: number; week: number };
    tasks: MyTask[];
};

function longDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString('de-AT', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });
}

function hoursLabel(value: number): string {
    return `${value.toLocaleString('de-AT')} h`;
}

/**
 * „Mein Tag" — Startseite für die Baustelle: heutige Einteilung mit
 * Kollegen und Fahrzeug, Stunden mit einem Tipper eintragen,
 * Regiebericht und Fotos gleich zur richtigen Baustelle, dazu die
 * eigenen Aufgaben. Fürs Handy gebaut: große Tipp-Flächen, eine Spalte.
 */
export default function MyDay({
    today,
    employee,
    vacation,
    assignments,
    next,
    hours,
    tasks,
}: Props) {
    const { auth } = usePage().props;
    const firstName = (employee?.name ?? auth.user.name).split(' ')[0];

    return (
        <>
            <Head title="Mein Tag" />
            <div className="flex h-full w-full max-w-2xl flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Hallo ${firstName}`}
                    description={longDate(today)}
                />

                {employee === null && (
                    <p className="-mt-4 rounded-lg border border-amber-500/50 bg-amber-50 p-3 text-sm dark:bg-amber-950/30">
                        Ihr Benutzerkonto ist noch keinem Mitarbeiter zugeordnet
                        — deshalb sehen Sie hier keine Einteilung. Bitte im Büro
                        Bescheid geben.
                    </p>
                )}

                {vacation && (
                    <p className="-mt-4 rounded-lg border border-sky-400/50 bg-sky-50 p-3 text-sm dark:bg-sky-950/30">
                        Sie haben Urlaub bis {formatDate(vacation.ends_on)}
                        {vacation.notes ? ` (${vacation.notes})` : ''}. Schöne
                        Erholung!
                    </p>
                )}

                {employee !== null && (
                    <section className="flex flex-col gap-3">
                        <Heading variant="small" title="Heute" />
                        {assignments.map((assignment) => (
                            <TodayCard
                                key={assignment.id}
                                assignment={assignment}
                                employeeId={employee.id}
                                today={today}
                            />
                        ))}
                        {assignments.length === 0 && !vacation && (
                            <p className="text-sm text-muted-foreground">
                                Für heute sind Sie nicht eingeteilt.
                            </p>
                        )}
                    </section>
                )}

                {next && (
                    <section className="flex flex-col gap-2">
                        <Heading
                            variant="small"
                            title={`Als Nächstes: ${longDate(next.date)}`}
                        />
                        {next.assignments.map((assignment) => (
                            <div
                                key={assignment.id}
                                className={`rounded-lg border border-l-4 border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border ${cardColor(assignment.color)}`}
                            >
                                <p className="font-medium">
                                    {assignment.label}
                                </p>
                                <Crew assignment={assignment} />
                            </div>
                        ))}
                    </section>
                )}

                <section className="flex flex-col gap-2">
                    <Heading variant="small" title="Meine Stunden" />
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                            <div className="text-xs text-muted-foreground">
                                Heute
                            </div>
                            <div className="text-2xl font-semibold">
                                {hoursLabel(hours.today)}
                            </div>
                        </div>
                        <div className="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                            <div className="text-xs text-muted-foreground">
                                Diese Woche
                            </div>
                            <div className="text-2xl font-semibold">
                                {hoursLabel(hours.week)}
                            </div>
                        </div>
                    </div>
                    <Button variant="outline" className="h-11" asChild>
                        <Link href={`/time-entries?date=${today}`}>
                            <Clock className="size-4" />
                            Zeiten erfassen oder ändern
                        </Link>
                    </Button>
                </section>

                <section className="flex flex-col gap-2">
                    <Heading
                        variant="small"
                        title="Meine Aufgaben"
                        description="Was mir zugeteilt und noch offen ist"
                    />
                    {tasks.map((task) => (
                        <TaskLine key={task.id} task={task} />
                    ))}
                    {tasks.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Nichts offen — sehr gut.
                        </p>
                    )}
                    <Link
                        href="/tasks"
                        className="text-sm text-muted-foreground underline underline-offset-2"
                    >
                        Alle Aufgaben der Firma
                    </Link>
                </section>

                <section className="grid grid-cols-2 gap-3">
                    <Button variant="outline" className="h-11" asChild>
                        <Link href="/assignments">
                            <CalendarDays className="size-4" />
                            Einteilung
                        </Link>
                    </Button>
                    <Button variant="outline" className="h-11" asChild>
                        <Link href="/site-reports/create">
                            <FileSignature className="size-4" />
                            Regiebericht
                        </Link>
                    </Button>
                </section>
            </div>
        </>
    );
}

function Crew({ assignment }: { assignment: AssignmentCard }) {
    return (
        <div className="mt-1 flex flex-col gap-0.5 text-sm text-muted-foreground">
            {assignment.colleagues.length > 0 && (
                <span className="flex items-start gap-1.5">
                    <Users className="mt-0.5 size-3.5 shrink-0" />
                    mit {assignment.colleagues.join(', ')}
                </span>
            )}
            {assignment.vehicles.length > 0 && (
                <span className="flex items-start gap-1.5">
                    <Truck className="mt-0.5 size-3.5 shrink-0" />
                    {assignment.vehicles.join(', ')}
                </span>
            )}
        </div>
    );
}

function TodayCard({
    assignment,
    employeeId,
    today,
}: {
    assignment: AssignmentCard;
    employeeId: number;
    today: string;
}) {
    const [hours, setHours] = useState('8');
    const [processing, setProcessing] = useState(false);

    const record = () => {
        setProcessing(true);
        router.post(
            '/time-entries',
            {
                project_id: assignment.project_id,
                employee_ids: [employeeId],
                work_date: today,
                hours,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div
            className={`rounded-lg border border-l-4 border-sidebar-border/70 p-4 dark:border-sidebar-border ${cardColor(assignment.color)}`}
        >
            <p className="text-lg leading-tight font-semibold">
                {assignment.label}
            </p>
            {assignment.project && assignment.project !== assignment.label && (
                <p className="text-sm text-muted-foreground">
                    {assignment.project}
                </p>
            )}
            <Crew assignment={assignment} />
            {assignment.notes && (
                <p className="mt-2 text-sm whitespace-pre-line">
                    {assignment.notes}
                </p>
            )}

            {assignment.project_id !== null && (
                <div className="mt-3 flex flex-col gap-2">
                    {assignment.hours_recorded > 0 ? (
                        <p className="flex items-center gap-1.5 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                            <Check className="size-4" />
                            {hoursLabel(assignment.hours_recorded)} erfasst
                        </p>
                    ) : (
                        <form
                            className="flex items-center gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                record();
                            }}
                        >
                            <Input
                                type="number"
                                inputMode="decimal"
                                step="0.25"
                                min={0.25}
                                max={24}
                                value={hours}
                                onChange={(event) =>
                                    setHours(event.target.value)
                                }
                                aria-label="Stunden"
                                className="h-11 w-20 bg-background"
                                required
                            />
                            <Button
                                type="submit"
                                className="h-11 flex-1"
                                disabled={processing}
                            >
                                <Clock className="size-4" />
                                Stunden eintragen
                            </Button>
                        </form>
                    )}
                    <div className="grid grid-cols-2 gap-2">
                        <Button
                            variant="outline"
                            className="h-11 bg-background"
                            asChild
                        >
                            <Link
                                href={`/site-reports/create?project=${assignment.project_id}`}
                            >
                                <FileSignature className="size-4" />
                                Regiebericht
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            className="h-11 bg-background"
                            asChild
                        >
                            <Link href={`/projects/${assignment.project_id}`}>
                                <Camera className="size-4" />
                                Fotos & Projekt
                            </Link>
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

function TaskLine({ task }: { task: MyTask }) {
    return (
        <div className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
            <Link
                href={`/projects/${task.project_id}`}
                className="min-w-0 flex-1"
            >
                <span className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{task.title}</span>
                    <Badge
                        variant={
                            task.kind === 'defect' ? 'destructive' : 'secondary'
                        }
                    >
                        {task.kind_label}
                    </Badge>
                </span>
                <span
                    className={`block text-sm ${task.overdue ? 'text-red-600' : 'text-muted-foreground'}`}
                >
                    {[
                        task.due_on
                            ? `${task.overdue ? 'überfällig seit' : 'fällig'} ${formatDate(task.due_on)}`
                            : null,
                        task.project,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
            </Link>
            <Button
                variant="outline"
                size="icon"
                className="size-11 shrink-0"
                aria-label={`${task.title} erledigen`}
                title="Erledigt"
                onClick={() =>
                    router.post(
                        `/tasks/${task.id}/complete`,
                        {},
                        { preserveScroll: true },
                    )
                }
            >
                <CircleCheck className="size-5" />
            </Button>
        </div>
    );
}

MyDay.layout = {
    breadcrumbs: [{ title: 'Mein Tag', href: dashboard() }],
};
