import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { DocumentsSection  } from '@/components/documents-section';
import type {DocumentItem} from '@/components/documents-section';
import Heading from '@/components/heading';
import { NotesSection } from '@/components/projects/notes-section';
import type { NoteItem } from '@/components/projects/notes-section';
import { TasksSection } from '@/components/projects/tasks-section';
import type { TaskItem } from '@/components/projects/tasks-section';
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
import { Separator } from '@/components/ui/separator';
import { formatDate, formatEUR } from '@/lib/format';

type Appointment = { id: number; on_date: string; label: string };
type ChangeOrderRow = {
    id: number;
    title: string;
    status: string;
    status_label: string;
    amount_net: string | null;
};
type ExternalOfferRow = {
    id: number;
    title: string;
    supplier: string | null;
    amount_net: string | null;
    status: string;
    status_label: string;
    valid_until: string | null;
};
type OpenItemRow = {
    id: number;
    number: string;
    doc_type_label: string;
    gross_effective: number;
    due_now: number;
    retained_open: number;
    status: string;
    status_label: string;
};
type IncomingRow = {
    id: number;
    supplier: string | null;
    supplier_invoice_no: string | null;
    gross: string;
    payment_status_label: string;
};

type Figures = {
    revenue_net: number;
    external_costs_net: number;
    hours: number;
    labor_cost: number;
    unrated_hours: number;
    contribution: number;
    margin_percent: number | null;
};

type Props = {
    project: {
        id: number;
        title: string;
        customer: string | null;
        site_address: string | null;
        description: string | null;
        responsible: string | null;
        commissioned_on: string | null;
        started_on: string | null;
        planned_finish_on: string | null;
        finished_on: string | null;
        status: string;
        status_label: string;
        warranty_until: string | null;
        notes: string | null;
    };
    appointments: Appointment[];
    changeOrders: ChangeOrderRow[];
    figures: Figures | null;
    externalOffers: ExternalOfferRow[] | null;
    openItems: OpenItemRow[] | null;
    incomingInvoices: IncomingRow[] | null;
    documents: DocumentItem[];
    tasks: TaskItem[];
    projectNotes: NoteItem[];
    members: { id: number; name: string }[];
    suppliers: { id: number; name: string }[];
    canWrite: boolean;
    canAttach: boolean;
    canViewFinancials: boolean;
};

const changeOrderStatuses = [
    { value: 'requested', label: 'Angefragt' },
    { value: 'offered', label: 'Angeboten' },
    { value: 'commissioned', label: 'Beauftragt' },
    { value: 'invoiced', label: 'Verrechnet' },
    { value: 'rejected', label: 'Abgelehnt' },
];

const externalOfferStatuses = [
    { value: 'received', label: 'Erhalten' },
    { value: 'commissioned', label: 'Beauftragt' },
    { value: 'rejected', label: 'Abgelehnt' },
];

export default function ProjectsShow({
    project,
    appointments,
    changeOrders,
    figures,
    externalOffers,
    openItems,
    incomingInvoices,
    documents,
    tasks,
    projectNotes,
    members,
    canWrite,
    canAttach,
    canViewFinancials,
    suppliers,
}: Props) {
    return (
        <>
            <Head title={`Projekt: ${project.title}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={project.title}
                        description={[project.customer, project.site_address]
                            .filter(Boolean)
                            .join(' · ')}
                    />
                    <div className="flex items-center gap-2">
                        <Badge variant="outline">{project.status_label}</Badge>
                        {canWrite && (
                            <Button variant="outline" asChild>
                                <Link href={`/projects/${project.id}/edit`}>
                                    <Pencil className="size-4" />
                                    Bearbeiten
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid max-w-3xl grid-cols-2 gap-x-8 gap-y-1 text-sm md:grid-cols-3">
                    <Fact label="Verantwortlich" value={project.responsible} />
                    <Fact label="Beauftragt" value={formatDate(project.commissioned_on)} />
                    <Fact label="Begonnen" value={formatDate(project.started_on)} />
                    <Fact label="Geplantes Ende" value={formatDate(project.planned_finish_on)} />
                    <Fact label="Fertiggestellt" value={formatDate(project.finished_on)} />
                    <Fact label="Gewährleistung bis" value={formatDate(project.warranty_until)} />
                </div>
                {project.description && (
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {project.description}
                    </p>
                )}

                {/* Projektzahlen mit Deckungsbeitrag (M8) — nur Finanzrollen */}
                {figures && (
                    <div className="flex max-w-3xl flex-wrap gap-3">
                        <FigureTile label="Erlöse (netto)" value={formatEUR(figures.revenue_net)} />
                        <FigureTile label="Fremdkosten (netto)" value={formatEUR(figures.external_costs_net)} />
                        <FigureTile
                            label={`Lohnkosten (${figures.hours.toLocaleString('de-AT')} h)`}
                            value={formatEUR(figures.labor_cost)}
                            hint={figures.unrated_hours > 0 ? `${figures.unrated_hours.toLocaleString('de-AT')} h ohne Stundensatz` : undefined}
                        />
                        <FigureTile
                            label={`Deckungsbeitrag${figures.margin_percent !== null ? ` (${figures.margin_percent.toLocaleString('de-AT')} %)` : ''}`}
                            value={formatEUR(figures.contribution)}
                            negative={figures.contribution < 0}
                        />
                    </div>
                )}

                <Separator />

                {/* Termine */}
                <Heading variant="small" title="Termine" description="Fließen später in die Fristenliste ein" />
                <div className="grid max-w-xl gap-2">
                    {appointments.map((appointment) => (
                        <div key={appointment.id} className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                            <span className="flex-1 font-medium">{appointment.label}</span>
                            <span className="text-sm text-muted-foreground">{formatDate(appointment.on_date)}</span>
                            {canWrite && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`${appointment.label} entfernen`}
                                    onClick={() => router.delete(`/projects/${project.id}/appointments/${appointment.id}`, { preserveScroll: true })}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            )}
                        </div>
                    ))}
                    {appointments.length === 0 && <p className="text-sm text-muted-foreground">Keine Termine.</p>}
                </div>
                {canWrite && <AddAppointmentForm projectId={project.id} />}

                <Separator />

                {/* Aufgaben & Mängel (M7) */}
                <TasksSection
                    projectId={project.id}
                    tasks={tasks}
                    members={members}
                />

                <Separator />

                {/* Nachträge */}
                <Heading variant="small" title="Nachträge" description={canViewFinancials ? 'Zusatzaufträge mit Statuslauf und Betrag' : 'Zusatzaufträge mit Statuslauf'} />
                <div className="grid max-w-xl gap-2">
                    {changeOrders.map((changeOrder) => (
                        <div key={changeOrder.id} className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                            <div className="flex-1">
                                <div className="font-medium">{changeOrder.title}</div>
                                {canViewFinancials && changeOrder.amount_net && (
                                    <div className="text-sm text-muted-foreground">{formatEUR(changeOrder.amount_net)} netto</div>
                                )}
                            </div>
                            {canWrite ? (
                                <Select
                                    defaultValue={changeOrder.status}
                                    onValueChange={(status) => router.patch(`/projects/${project.id}/change-orders/${changeOrder.id}`, { status, amount_net: changeOrder.amount_net }, { preserveScroll: true })}
                                >
                                    <SelectTrigger className="w-40" aria-label={`Status von ${changeOrder.title}`}>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {changeOrderStatuses.map((status) => (
                                            <SelectItem key={status.value} value={status.value}>{status.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Badge variant="outline">{changeOrder.status_label}</Badge>
                            )}
                        </div>
                    ))}
                    {changeOrders.length === 0 && <p className="text-sm text-muted-foreground">Keine Nachträge.</p>}
                </div>
                {canWrite && <AddChangeOrderForm projectId={project.id} showAmount={canViewFinancials} />}

                {canViewFinancials && externalOffers !== null && (
                    <>
                        <Separator />
                        <Heading variant="small" title="Fremdangebote" description="Eingeholte Angebote von Lieferanten und Subunternehmern" />
                        <div className="grid max-w-xl gap-2">
                            {externalOffers.map((externalOffer) => (
                                <div key={externalOffer.id} className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                                    <div className="flex-1">
                                        <div className="font-medium">{externalOffer.title}</div>
                                        <div className="text-sm text-muted-foreground">
                                            {externalOffer.supplier}
                                            {externalOffer.amount_net && ` · ${formatEUR(externalOffer.amount_net)} netto`}
                                            {externalOffer.valid_until && ` · gültig bis ${formatDate(externalOffer.valid_until)}`}
                                        </div>
                                    </div>
                                    {canWrite ? (
                                        <Select
                                            defaultValue={externalOffer.status}
                                            onValueChange={(status) => router.patch(`/projects/${project.id}/external-offers/${externalOffer.id}`, { status }, { preserveScroll: true })}
                                        >
                                            <SelectTrigger className="w-36" aria-label={`Status von ${externalOffer.title}`}>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {externalOfferStatuses.map((status) => (
                                                    <SelectItem key={status.value} value={status.value}>{status.label}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <Badge variant="outline">{externalOffer.status_label}</Badge>
                                    )}
                                </div>
                            ))}
                            {externalOffers.length === 0 && <p className="text-sm text-muted-foreground">Keine Fremdangebote.</p>}
                        </div>
                        {canWrite && <AddExternalOfferForm projectId={project.id} suppliers={suppliers} />}
                    </>
                )}

                {canViewFinancials && openItems !== null && (
                    <>
                        <Separator />
                        <Heading variant="small" title="Ausgangsrechnungen" description="Belege dieses Projekts mit Zahlungsstand" />
                        <div className="grid max-w-3xl gap-2">
                            {openItems.map((row) => (
                                <Link key={row.id} href={`/outgoing-invoices/${row.id}`} className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 text-sm hover:bg-accent/50 dark:border-sidebar-border">
                                    <span className="font-medium">{row.number}</span>
                                    <Badge variant="outline">{row.doc_type_label}</Badge>
                                    <span className="flex-1" />
                                    <span>{formatEUR(row.gross_effective)}</span>
                                    <span className="text-muted-foreground">fällig: {formatEUR(row.due_now)}</span>
                                    <Badge variant="secondary">{row.status_label}</Badge>
                                </Link>
                            ))}
                            {openItems.length === 0 && <p className="text-sm text-muted-foreground">Noch keine Rechnungen.</p>}
                        </div>
                    </>
                )}

                {canViewFinancials && incomingInvoices !== null && (
                    <>
                        <Separator />
                        <Heading variant="small" title="Eingangsrechnungen" description="Kosten dieses Projekts" />
                        <div className="grid max-w-3xl gap-2">
                            {incomingInvoices.map((row) => (
                                <Link key={row.id} href={`/incoming-invoices/${row.id}/edit`} className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 text-sm hover:bg-accent/50 dark:border-sidebar-border">
                                    <span className="font-medium">{row.supplier}</span>
                                    <span className="text-muted-foreground">{row.supplier_invoice_no}</span>
                                    <span className="flex-1" />
                                    <span>{formatEUR(row.gross)}</span>
                                    <Badge variant="secondary">{row.payment_status_label}</Badge>
                                </Link>
                            ))}
                            {incomingInvoices.length === 0 && <p className="text-sm text-muted-foreground">Noch keine Eingangsrechnungen.</p>}
                        </div>
                    </>
                )}

                <Separator />

                <DocumentsSection
                    documentableType="project"
                    documentableId={project.id}
                    documents={documents}
                    canWrite={canWrite}
                    canUpload={canAttach}
                    defaultCategory="plan"
                    photoGallery
                />

                <Separator />

                {/* Notizen (M7) */}
                <NotesSection projectId={project.id} notes={projectNotes} />
            </div>
        </>
    );
}

function FigureTile({
    label,
    value,
    hint,
    negative = false,
}: {
    label: string;
    value: string;
    hint?: string;
    negative?: boolean;
}) {
    return (
        <div className="min-w-40 rounded-lg border border-sidebar-border/70 px-4 py-2 dark:border-sidebar-border">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div
                className={`text-lg font-semibold ${negative ? 'text-red-600 dark:text-red-400' : ''}`}
            >
                {value}
            </div>
            {hint && <div className="text-xs text-amber-600 dark:text-amber-400">{hint}</div>}
        </div>
    );
}

function Fact({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex justify-between gap-2 border-b border-dashed border-sidebar-border/50 py-1">
            <span className="text-muted-foreground">{label}</span>
            <span>{value ?? '—'}</span>
        </div>
    );
}

function AddAppointmentForm({ projectId }: { projectId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({ label: '', on_date: '' });

    return (
        <form
            className="flex max-w-xl items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/projects/${projectId}/appointments`, { preserveScroll: true, onSuccess: () => reset() });
            }}
        >
            <div className="grid flex-1 gap-2">
                <Label htmlFor="appointment-label">Termin</Label>
                <Input id="appointment-label" value={data.label} onChange={(e) => setData('label', e.target.value)} placeholder="z. B. Abnahme" required />
                <InputError message={errors.label ?? errors.on_date} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="appointment-date">Datum</Label>
                <Input id="appointment-date" type="date" value={data.on_date} onChange={(e) => setData('on_date', e.target.value)} required />
            </div>
            <Button type="submit" disabled={processing}>Hinzufügen</Button>
        </form>
    );
}

function AddChangeOrderForm({ projectId, showAmount }: { projectId: number; showAmount: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm({ title: '', amount_net: '', status: 'requested' });

    return (
        <form
            className="flex max-w-xl items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/projects/${projectId}/change-orders`, { preserveScroll: true, onSuccess: () => reset() });
            }}
        >
            <div className="grid flex-1 gap-2">
                <Label htmlFor="change-order-title">Neuer Nachtrag</Label>
                <Input id="change-order-title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                <InputError message={errors.title ?? errors.amount_net} />
            </div>
            {showAmount && (
                <div className="grid w-36 gap-2">
                    <Label htmlFor="change-order-amount">Netto (€)</Label>
                    <Input id="change-order-amount" type="number" step="0.01" min={0} value={data.amount_net} onChange={(e) => setData('amount_net', e.target.value)} />
                </div>
            )}
            <Button type="submit" disabled={processing}>Hinzufügen</Button>
        </form>
    );
}

function AddExternalOfferForm({ projectId, suppliers }: { projectId: number; suppliers: { id: number; name: string }[] }) {
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        supplier_id: '',
        title: '',
        amount_net: '',
        received_on: '',
    });

    return (
        <form
            className="flex max-w-xl flex-wrap items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                transform((values) => ({ ...values, supplier_id: values.supplier_id ? Number(values.supplier_id) : null }));
                post(`/projects/${projectId}/external-offers`, { preserveScroll: true, onSuccess: () => reset() });
            }}
        >
            <div className="grid flex-1 gap-2">
                <Label htmlFor="external-offer-title">Neues Fremdangebot</Label>
                <Input id="external-offer-title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                <InputError message={errors.title ?? errors.supplier_id ?? errors.amount_net} />
            </div>
            <div className="grid w-48 gap-2">
                <Label>Lieferant</Label>
                <Select value={data.supplier_id} onValueChange={(value) => setData('supplier_id', value)}>
                    <SelectTrigger aria-label="Lieferant">
                        <SelectValue placeholder="Wählen" />
                    </SelectTrigger>
                    <SelectContent>
                        {suppliers.map((supplier) => (
                            <SelectItem key={supplier.id} value={String(supplier.id)}>{supplier.name}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="grid w-32 gap-2">
                <Label htmlFor="external-offer-amount">Netto (€)</Label>
                <Input id="external-offer-amount" type="number" step="0.01" min={0} value={data.amount_net} onChange={(e) => setData('amount_net', e.target.value)} />
            </div>
            <Button type="submit" disabled={processing}>Hinzufügen</Button>
        </form>
    );
}

ProjectsShow.layout = ({ project }: Props) => ({
    breadcrumbs: [
        { title: 'Projekte', href: '/projects' },
        { title: project.title, href: `/projects/${project.id}` },
    ],
});
