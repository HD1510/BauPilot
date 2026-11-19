import { Head, router, useForm } from '@inertiajs/react';
import {
    Car,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    Printer,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CARD_COLORS } from '@/lib/assignment-colors';
import { confirmDelete } from '@/lib/confirm-delete';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';

type Option = { id: number; name: string };
type VehicleOption = { id: number; plate: string };
type ProjectOption = { id: number; title: string };

type VacationRow = {
    employee_id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    notes: string | null;
};

// ISO-Daten vergleichen sich als Text korrekt.
const onVacation = (
    vacations: VacationRow[],
    employeeId: number,
    from: string,
    until?: string,
) =>
    vacations.some(
        (vacation) =>
            vacation.employee_id === employeeId &&
            vacation.starts_on <= (until || from) &&
            vacation.ends_on >= from,
    );

const shortDay = (iso: string) =>
    `${Number(iso.slice(8, 10))}.${Number(iso.slice(5, 7))}.`;

const nextDay = (iso: string) => {
    const date = new Date(`${iso}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + 1);

    return date.toISOString().slice(0, 10);
};

type VacationInfo = {
    /** ganz = an jedem Tag des Zeitraums im Urlaub, teils = nur an manchen */
    kind: 'none' | 'partial' | 'full';
    /** Urlaubstage im Zeitraum, zusammengefasst: „18.11.–20.11." */
    when: string;
    notes: string | null;
};

/**
 * Urlaub eines Mitarbeiters im gewählten Zeitraum. Ist er nur an
 * einzelnen Tagen weg, bleibt er wählbar — eingeteilt wird er dann an
 * den übrigen Tagen.
 */
const vacationInfo = (
    vacations: VacationRow[],
    employeeId: number,
    from: string,
    until?: string,
): VacationInfo => {
    const own = vacations.filter(
        (vacation) => vacation.employee_id === employeeId,
    );
    const last = until && until >= from ? until : from;
    const away: string[] = [];
    let total = 0;
    let notes: string | null = null;

    // Mehr als zwei Monate plant niemand in einem Zug — Schutz vor Tippfehlern.
    for (let day = from; day <= last && total < 62; day = nextDay(day)) {
        total++;
        const hit = own.find(
            (vacation) => vacation.starts_on <= day && vacation.ends_on >= day,
        );

        if (hit) {
            away.push(day);
            notes = notes ?? hit.notes;
        }
    }

    if (away.length === 0) {
        return { kind: 'none', when: '', notes: null };
    }

    // Aufeinanderfolgende Tage zu Zeiträumen zusammenfassen.
    const ranges: string[] = [];
    let start = away[0];
    let previous = away[0];

    for (const day of [...away.slice(1), '']) {
        if (day !== nextDay(previous)) {
            ranges.push(
                start === previous
                    ? shortDay(start)
                    : `${shortDay(start)}–${shortDay(previous)}`,
            );
            start = day;
        }

        previous = day;
    }

    return {
        kind: away.length === total ? 'full' : 'partial',
        when: ranges.join(', '),
        notes,
    };
};

type AssignmentRow = {
    id: number;
    work_date: string;
    project_id: number | null;
    site: string | null;
    label: string;
    notes: string | null;
    color: number;
    employees: { id: number; name: string }[];
    vehicles: { id: number; plate: string }[];
};

type Props = {
    week: { monday: string; previous: string; next: string; today: string };
    days: string[];
    assignments: AssignmentRow[];
    employees: Option[];
    vehicles: VehicleOption[];
    projects: ProjectOption[];
    vacations: VacationRow[];
    canWrite: boolean;
};

const dayLabel = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString('de-AT', {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
    });

export default function AssignmentsIndex({
    week,
    days,
    assignments,
    employees,
    vehicles,
    projects,
    vacations,
    canWrite,
}: Props) {
    const [editing, setEditing] = useState<AssignmentRow | null>(null);
    const [formDate, setFormDate] = useState<string>(week.today);
    const [dropDay, setDropDay] = useState<string | null>(null);
    const [showWeekend, setShowWeekend] = useState(false);
    const [formFocus, setFormFocus] = useState(0);
    const formRef = useRef<HTMLDivElement>(null);

    // Das Formular steht unter dem Wochenraster — nach Plus oder Stift
    // dorthin springen, sonst passiert scheinbar nichts.
    const openForm = (assignment: AssignmentRow | null, day: string) => {
        setEditing(assignment);
        setFormDate(day);
        setFormFocus((tick) => tick + 1);
    };

    useEffect(() => {
        if (formFocus === 0) {
            return;
        }

        formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document
            .getElementById('assignment-site')
            ?.focus({ preventScroll: true });
    }, [formFocus]);

    // Samstag und Sonntag nur zeigen, wenn dort etwas steht (oder auf
    // Wunsch) — die Werktage bekommen dafür die ganze Breite.
    const visibleDays = days.filter(
        (day, index) =>
            index < 5 ||
            showWeekend ||
            day === week.today ||
            assignments.some((assignment) => assignment.work_date === day),
    );
    const weekendHidden = visibleDays.length < days.length;
    const gridColumns =
        { 5: 'xl:grid-cols-5', 6: 'xl:grid-cols-6', 7: 'xl:grid-cols-7' }[
            visibleDays.length
        ] ?? 'xl:grid-cols-7';

    const moveAssignment = (id: number, day: string, position?: number) => {
        router.patch(
            `/assignments/${id}/move`,
            { work_date: day, position: position ?? null },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Einteilung" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        title="Einteilung"
                        description="Wer ist an welchem Tag auf welcher Baustelle — samt Fahrzeug"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        {(weekendHidden || showWeekend) && (
                            <Button
                                variant="ghost"
                                onClick={() => setShowWeekend(!showWeekend)}
                            >
                                {showWeekend
                                    ? 'Wochenende ausblenden'
                                    : 'Wochenende einblenden'}
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <a
                                href={`/assignments/print?date=${week.monday}`}
                                target="_blank"
                                rel="noopener"
                            >
                                <Printer className="size-4" />
                                Drucken / PDF
                            </a>
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Vorige Woche"
                            onClick={() =>
                                router.get(
                                    '/assignments',
                                    { date: week.previous },
                                    { preserveState: false },
                                )
                            }
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get(
                                    '/assignments',
                                    { date: week.today },
                                    { preserveState: false },
                                )
                            }
                        >
                            Heute
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Nächste Woche"
                            onClick={() =>
                                router.get(
                                    '/assignments',
                                    { date: week.next },
                                    { preserveState: false },
                                )
                            }
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                </div>

                <div className={`grid gap-3 md:grid-cols-2 ${gridColumns}`}>
                    {visibleDays.map((day) => {
                        const dayAssignments = assignments.filter(
                            (assignment) => assignment.work_date === day,
                        );
                        const dayVacationers = vacations
                            .filter(
                                (vacation) =>
                                    vacation.starts_on <= day &&
                                    vacation.ends_on >= day,
                            )
                            .map(
                                (vacation) =>
                                    vacation.name +
                                    (vacation.notes
                                        ? ` (${vacation.notes})`
                                        : ''),
                            )
                            .sort();

                        return (
                            <div
                                key={day}
                                className={`grid min-w-0 content-start gap-2 rounded-lg border p-2 dark:border-sidebar-border ${
                                    dropDay === day
                                        ? 'border-primary bg-primary/5'
                                        : day === week.today
                                          ? 'border-primary'
                                          : 'border-sidebar-border/70'
                                }`}
                                onDragOver={(event) => {
                                    if (!canWrite) {
                                        return;
                                    }

                                    event.preventDefault();
                                    setDropDay(day);
                                }}
                                onDragLeave={(event) => {
                                    if (
                                        !event.currentTarget.contains(
                                            event.relatedTarget as Node | null,
                                        )
                                    ) {
                                        setDropDay(null);
                                    }
                                }}
                                onDrop={(event) => {
                                    event.preventDefault();
                                    setDropDay(null);
                                    const id = Number(
                                        event.dataTransfer.getData(
                                            'text/plain',
                                        ),
                                    );

                                    if (canWrite && id > 0) {
                                        moveAssignment(id, day);
                                    }
                                }}
                            >
                                <div className="flex items-center justify-between gap-1">
                                    <span className="text-sm font-semibold">
                                        {dayLabel(day)}
                                    </span>
                                    {canWrite && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 xl:size-6"
                                            aria-label={`Einteilung für ${dayLabel(day)} anlegen`}
                                            onClick={() => openForm(null, day)}
                                        >
                                            <Plus className="size-4" />
                                        </Button>
                                    )}
                                </div>
                                {dayVacationers.length > 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        Urlaub: {dayVacationers.join(', ')}
                                    </p>
                                )}
                                {dayAssignments.map((assignment, index) => (
                                    <div
                                        key={assignment.id}
                                        className={`grid min-w-0 gap-1 rounded-md border border-l-4 border-sidebar-border/70 p-2 text-sm break-words dark:border-sidebar-border ${
                                            CARD_COLORS[
                                                assignment.color %
                                                    CARD_COLORS.length
                                            ]
                                        } ${canWrite ? 'cursor-grab' : ''}`}
                                        draggable={canWrite}
                                        onDragStart={(event) => {
                                            event.dataTransfer.setData(
                                                'text/plain',
                                                String(assignment.id),
                                            );
                                            event.dataTransfer.effectAllowed =
                                                'move';
                                        }}
                                        onDrop={(event) => {
                                            // Auf einer Karte abgelegt:
                                            // an deren Stelle einsortieren.
                                            event.preventDefault();
                                            event.stopPropagation();
                                            setDropDay(null);
                                            const id = Number(
                                                event.dataTransfer.getData(
                                                    'text/plain',
                                                ),
                                            );

                                            if (
                                                canWrite &&
                                                id > 0 &&
                                                id !== assignment.id
                                            ) {
                                                moveAssignment(id, day, index);
                                            }
                                        }}
                                    >
                                        <div className="flex items-start justify-between gap-1">
                                            <span className="font-medium">
                                                {assignment.label}
                                            </span>
                                            {canWrite && (
                                                <span className="flex shrink-0">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 xl:size-6"
                                                        aria-label={`${assignment.label} bearbeiten`}
                                                        onClick={() =>
                                                            openForm(
                                                                assignment,
                                                                assignment.work_date,
                                                            )
                                                        }
                                                    >
                                                        <Pencil className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 xl:size-6"
                                                        aria-label={`${assignment.label} entfernen`}
                                                        onClick={() =>
                                                            confirmDelete(
                                                                `/assignments/${assignment.id}`,
                                                                `Einteilung „${assignment.label}“ wirklich entfernen?`,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap gap-1">
                                            {assignment.employees.map(
                                                (employee) => {
                                                    const vacationing =
                                                        onVacation(
                                                            vacations,
                                                            employee.id,
                                                            day,
                                                        );

                                                    return (
                                                        <Badge
                                                            key={employee.id}
                                                            variant="secondary"
                                                            className={`max-w-full whitespace-normal ${
                                                                vacationing
                                                                    ? 'opacity-50'
                                                                    : ''
                                                            }`}
                                                        >
                                                            {employee.name}
                                                            {vacationing &&
                                                                ' (Urlaub)'}
                                                        </Badge>
                                                    );
                                                },
                                            )}
                                            {assignment.employees.length ===
                                                0 && (
                                                <span className="text-xs text-muted-foreground">
                                                    Noch niemand eingeteilt
                                                </span>
                                            )}
                                        </div>
                                        {assignment.vehicles.length > 0 && (
                                            <div className="flex flex-wrap gap-1">
                                                {assignment.vehicles.map(
                                                    (vehicle) => (
                                                        <Badge
                                                            key={vehicle.id}
                                                            variant="outline"
                                                            className="max-w-full whitespace-normal"
                                                        >
                                                            <Car className="size-3" />
                                                            {vehicle.plate}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                        {assignment.notes && (
                                            <p className="text-xs text-muted-foreground">
                                                {assignment.notes}
                                            </p>
                                        )}
                                    </div>
                                ))}
                                {dayAssignments.length === 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        —
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>

                {canWrite && (
                    <>
                        <Separator />
                        <div ref={formRef} className="scroll-mt-4" />
                        <AssignmentForm
                            key={editing?.id ?? `new-${formDate}`}
                            assignment={editing}
                            date={formDate}
                            employees={employees}
                            vehicles={vehicles}
                            projects={projects}
                            vacations={vacations}
                            onDone={() => setEditing(null)}
                        />
                    </>
                )}
            </div>
        </>
    );
}

function AssignmentForm({
    assignment,
    date,
    employees,
    vehicles,
    projects,
    vacations,
    onDone,
}: {
    assignment: AssignmentRow | null;
    date: string;
    employees: Option[];
    vehicles: VehicleOption[];
    projects: ProjectOption[];
    vacations: VacationRow[];
    onDone: () => void;
}) {
    const { data, setData, post, patch, processing, errors, reset, transform } =
        useForm<{
            work_date: string;
            work_date_until: string;
            project_id: string;
            site: string;
            notes: string;
            employee_ids: number[];
            vehicle_ids: number[];
        }>({
            work_date: assignment?.work_date ?? date,
            work_date_until: '',
            project_id: assignment?.project_id
                ? String(assignment.project_id)
                : 'none',
            site: assignment?.site ?? '',
            notes: assignment?.notes ?? '',
            employee_ids:
                assignment?.employees.map((employee) => employee.id) ?? [],
            vehicle_ids:
                assignment?.vehicles.map((vehicle) => vehicle.id) ?? [],
        });

    // "none" ist nur der Platzhalter des Selects — am Server zählt null.
    transform((current) => ({
        ...current,
        project_id: current.project_id === 'none' ? null : current.project_id,
        work_date_until: current.work_date_until || null,
    }));

    // Gewählte Mitarbeiter, die nur an einzelnen Tagen des Zeitraums
    // im Urlaub sind — dafür gibt es unter der Liste einen Hinweis.
    const partlyAway = employees
        .filter(
            (employee) =>
                data.employee_ids.includes(employee.id) &&
                vacationInfo(
                    vacations,
                    employee.id,
                    data.work_date,
                    assignment ? undefined : data.work_date_until,
                ).kind === 'partial',
        )
        .map((employee) => employee.name);

    const toggle = (key: 'employee_ids' | 'vehicle_ids', id: number) => {
        setData(
            key,
            data[key].includes(id)
                ? data[key].filter((value) => value !== id)
                : [...data[key], id],
        );
    };

    return (
        <form
            className="grid max-w-4xl gap-4"
            onSubmit={(event) => {
                event.preventDefault();

                const options = {
                    preserveScroll: true,
                    onSuccess: () => {
                        reset();
                        onDone();
                    },
                };

                if (assignment) {
                    patch(`/assignments/${assignment.id}`, options);
                } else {
                    post('/assignments', options);
                }
            }}
        >
            <Heading
                variant="small"
                title={
                    assignment
                        ? `Einteilung bearbeiten: ${assignment.label}`
                        : 'Neue Einteilung'
                }
                description="Baustelle frei eintragen oder ein Projekt wählen — mit „bis“ gleich für mehrere Tage"
            />
            <fieldset className="grid gap-4" disabled={processing}>
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="assignment-date">Tag</Label>
                        <Input
                            id="assignment-date"
                            type="date"
                            value={data.work_date}
                            onChange={(e) =>
                                setData('work_date', e.target.value)
                            }
                            required
                        />
                        <InputError message={errors.work_date} />
                    </div>
                    {!assignment && (
                        <div className="grid gap-2">
                            <Label htmlFor="assignment-date-until">
                                bis (optional)
                            </Label>
                            <Input
                                id="assignment-date-until"
                                type="date"
                                value={data.work_date_until}
                                min={data.work_date}
                                onChange={(e) =>
                                    setData('work_date_until', e.target.value)
                                }
                            />
                            <InputError message={errors.work_date_until} />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="assignment-project">
                            Projekt (optional)
                        </Label>
                        <Select
                            value={data.project_id}
                            onValueChange={(value) =>
                                setData('project_id', value)
                            }
                        >
                            <SelectTrigger id="assignment-project">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Kein Projekt
                                </SelectItem>
                                {projects.map((project) => (
                                    <SelectItem
                                        key={project.id}
                                        value={String(project.id)}
                                    >
                                        {project.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.project_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="assignment-site">Baustelle</Label>
                        <Input
                            id="assignment-site"
                            value={data.site}
                            onChange={(e) => setData('site', e.target.value)}
                            placeholder="z. B. EFH Huber, Bad Sauerbrunn"
                        />
                        <InputError message={errors.site} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label>Mitarbeiter</Label>
                    <div className="flex flex-wrap gap-x-4 gap-y-2">
                        {employees.map((employee) => {
                            // Ganz im Urlaub: ausgegraut und nicht wählbar.
                            // Nur an einzelnen Tagen des Zeitraums: wählbar
                            // mit Hinweis — eingeteilt wird an den übrigen.
                            const vacation = vacationInfo(
                                vacations,
                                employee.id,
                                data.work_date,
                                assignment ? undefined : data.work_date_until,
                            );
                            const away = vacation.kind === 'full';

                            return (
                                <Label
                                    key={employee.id}
                                    className={`flex items-center gap-2 text-sm font-normal ${
                                        away
                                            ? 'text-muted-foreground opacity-60'
                                            : ''
                                    }`}
                                >
                                    <Checkbox
                                        checked={data.employee_ids.includes(
                                            employee.id,
                                        )}
                                        disabled={
                                            away &&
                                            !data.employee_ids.includes(
                                                employee.id,
                                            )
                                        }
                                        onCheckedChange={() =>
                                            toggle('employee_ids', employee.id)
                                        }
                                    />
                                    {employee.name}
                                    {away &&
                                        ` (Urlaub${vacation.notes ? `: ${vacation.notes}` : ''})`}
                                    {vacation.kind === 'partial' && (
                                        <span className="text-amber-700 dark:text-amber-400">
                                            (Urlaub {vacation.when}
                                            {vacation.notes
                                                ? `: ${vacation.notes}`
                                                : ''}
                                            )
                                        </span>
                                    )}
                                </Label>
                            );
                        })}
                        {employees.length === 0 && (
                            <span className="text-sm text-muted-foreground">
                                Noch keine planbaren Mitarbeiter angelegt.
                            </span>
                        )}
                    </div>
                    {partlyAway.length > 0 && (
                        <p className="text-sm text-amber-700 dark:text-amber-400">
                            {partlyAway.join(', ')}{' '}
                            {partlyAway.length === 1 ? 'wird' : 'werden'} an den
                            Urlaubstagen nicht eingeteilt, an den übrigen Tagen
                            schon.
                        </p>
                    )}
                    <InputError message={errors.employee_ids} />
                </div>

                <div className="grid gap-2">
                    <Label>Fahrzeuge</Label>
                    <div className="flex flex-wrap gap-x-4 gap-y-2">
                        {vehicles.map((vehicle) => (
                            <Label
                                key={vehicle.id}
                                className="flex items-center gap-2 text-sm font-normal"
                            >
                                <Checkbox
                                    checked={data.vehicle_ids.includes(
                                        vehicle.id,
                                    )}
                                    onCheckedChange={() =>
                                        toggle('vehicle_ids', vehicle.id)
                                    }
                                />
                                {vehicle.plate}
                            </Label>
                        ))}
                        {vehicles.length === 0 && (
                            <span className="text-sm text-muted-foreground">
                                Noch keine Fahrzeuge angelegt.
                            </span>
                        )}
                    </div>
                    <InputError message={errors.vehicle_ids} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="assignment-notes">Notiz</Label>
                    <Textarea
                        id="assignment-notes"
                        rows={2}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder="z. B. Material mitnehmen, Treffpunkt 6:30"
                    />
                    <InputError message={errors.notes} />
                </div>

                <div className="flex gap-2">
                    {assignment && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onDone}
                        >
                            Abbrechen
                        </Button>
                    )}
                    <Button type="submit">
                        <Plus className="size-4" />
                        {assignment
                            ? 'Einteilung speichern'
                            : 'Einteilung anlegen'}
                    </Button>
                </div>
            </fieldset>
        </form>
    );
}

AssignmentsIndex.layout = {
    breadcrumbs: [{ title: 'Einteilung', href: '/assignments' }],
};
