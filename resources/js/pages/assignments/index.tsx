import { Head, router, useForm } from '@inertiajs/react';
import {
    Car,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    TriangleAlert,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
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

type AssignmentRow = {
    id: number;
    work_date: string;
    project_id: number | null;
    site: string | null;
    label: string;
    notes: string | null;
    employees: { id: number; name: string; conflict: boolean }[];
    vehicles: { id: number; plate: string; conflict: boolean }[];
};

type Props = {
    week: { monday: string; previous: string; next: string; today: string };
    days: string[];
    assignments: AssignmentRow[];
    employees: Option[];
    vehicles: VehicleOption[];
    projects: ProjectOption[];
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
    canWrite,
}: Props) {
    const [editing, setEditing] = useState<AssignmentRow | null>(null);
    const [formDate, setFormDate] = useState<string>(week.today);

    return (
        <>
            <Head title="Einteilung" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        title="Einteilung"
                        description="Wer ist an welchem Tag auf welcher Baustelle — samt Fahrzeug"
                    />
                    <div className="flex items-center gap-2">
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

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
                    {days.map((day) => {
                        const dayAssignments = assignments.filter(
                            (assignment) => assignment.work_date === day,
                        );

                        return (
                            <div
                                key={day}
                                className={`grid content-start gap-2 rounded-lg border p-2 dark:border-sidebar-border ${
                                    day === week.today
                                        ? 'border-primary'
                                        : 'border-sidebar-border/70'
                                }`}
                            >
                                <div className="flex items-center justify-between gap-1">
                                    <span className="text-sm font-semibold">
                                        {dayLabel(day)}
                                    </span>
                                    {canWrite && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-6"
                                            aria-label={`Einteilung für ${dayLabel(day)} anlegen`}
                                            onClick={() => {
                                                setEditing(null);
                                                setFormDate(day);
                                            }}
                                        >
                                            <Plus className="size-4" />
                                        </Button>
                                    )}
                                </div>
                                {dayAssignments.map((assignment) => (
                                    <div
                                        key={assignment.id}
                                        className="grid gap-1 rounded-md border border-sidebar-border/70 p-2 text-sm dark:border-sidebar-border"
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
                                                        className="size-6"
                                                        aria-label={`${assignment.label} bearbeiten`}
                                                        onClick={() => {
                                                            setEditing(
                                                                assignment,
                                                            );
                                                            setFormDate(
                                                                assignment.work_date,
                                                            );
                                                        }}
                                                    >
                                                        <Pencil className="size-3.5" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-6"
                                                        aria-label={`${assignment.label} entfernen`}
                                                        onClick={() =>
                                                            router.delete(
                                                                `/assignments/${assignment.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
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
                                                (employee) => (
                                                    <Badge
                                                        key={employee.id}
                                                        variant={
                                                            employee.conflict
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                        title={
                                                            employee.conflict
                                                                ? 'An diesem Tag mehrfach eingeteilt'
                                                                : undefined
                                                        }
                                                    >
                                                        {employee.conflict && (
                                                            <TriangleAlert className="size-3" />
                                                        )}
                                                        {employee.name}
                                                    </Badge>
                                                ),
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
                                                            variant={
                                                                vehicle.conflict
                                                                    ? 'destructive'
                                                                    : 'outline'
                                                            }
                                                            title={
                                                                vehicle.conflict
                                                                    ? 'An diesem Tag mehrfach eingeteilt'
                                                                    : undefined
                                                            }
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
                        <AssignmentForm
                            key={editing?.id ?? `new-${formDate}`}
                            assignment={editing}
                            date={formDate}
                            employees={employees}
                            vehicles={vehicles}
                            projects={projects}
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
    onDone,
}: {
    assignment: AssignmentRow | null;
    date: string;
    employees: Option[];
    vehicles: VehicleOption[];
    projects: ProjectOption[];
    onDone: () => void;
}) {
    const { data, setData, post, patch, processing, errors, reset, transform } =
        useForm<{
            work_date: string;
            project_id: string;
            site: string;
            notes: string;
            employee_ids: number[];
            vehicle_ids: number[];
        }>({
            work_date: assignment?.work_date ?? date,
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
    }));

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
                description="Baustelle frei eintragen oder ein Projekt wählen — doppelt eingeteilte Mitarbeiter werden markiert"
            />
            <fieldset className="grid gap-4" disabled={processing}>
                <div className="grid grid-cols-3 gap-4">
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
                        {employees.map((employee) => (
                            <Label
                                key={employee.id}
                                className="flex items-center gap-2 text-sm font-normal"
                            >
                                <Checkbox
                                    checked={data.employee_ids.includes(
                                        employee.id,
                                    )}
                                    onCheckedChange={() =>
                                        toggle('employee_ids', employee.id)
                                    }
                                />
                                {employee.name}
                            </Label>
                        ))}
                        {employees.length === 0 && (
                            <span className="text-sm text-muted-foreground">
                                Noch keine Mitarbeiter angelegt.
                            </span>
                        )}
                    </div>
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
