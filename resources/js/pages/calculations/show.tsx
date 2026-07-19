import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileText, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
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
import { formatEUR } from '@/lib/format';

type OptionValue = { value: string; label: string };
type ProjectOption = { id: number; title: string };

type Quantities = {
    area: number;
    perimeter: number;
    parquet_area: number;
    floor_tile_area_raw: number;
    floor_tile_area: number;
    wall_tile_area_raw: number;
    wall_tile_area: number;
    silicone: number;
    skirting_parquet: number;
    skirting_tiles: number;
    skirting: number;
    painting_area: number;
    cost: number;
};

type Room = {
    id: number;
    name: string;
    shape: string;
    shape_label: string;
    material: string;
    material_label: string;
    length: string | null;
    width: string | null;
    height: string;
    length2: string | null;
    width2: string | null;
    depth: string | null;
    area_manual: string | null;
    perimeter_manual: string | null;
    edges: number;
    door_width: string;
    estimated: boolean;
    quantities: Quantities;
};

type CalculationData = {
    id: number;
    name: string;
    project_id: number | null;
    waste_percent: string;
    wall_tile_height: string;
    price_parquet: string;
    price_floor_tiles: string;
    price_wall_tiles: string;
    price_silicone: string;
    price_skirting: string;
    price_painting: string;
    notes: string | null;
    lock_version: number;
};

type Props = {
    calculation: CalculationData;
    rooms: Room[];
    totals: Quantities;
    projects: ProjectOption[];
    shapes: OptionValue[];
    materials: OptionValue[];
};

export default function CalculationsShow({
    calculation,
    rooms,
    totals,
    projects,
    shapes,
    materials,
}: Props) {
    const [editRoom, setEditRoom] = useState<Room | null>(null);

    return (
        <>
            <Head title={`Baukalkulation: ${calculation.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={calculation.name}
                        description="Räume erfassen — Mengen und Kosten rechnet BauPilot laufend mit"
                    />
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Kalkulation löschen"
                        onClick={() => {
                            if (
                                confirm(
                                    'Kalkulation samt Räumen wirklich löschen?',
                                )
                            ) {
                                router.delete(
                                    `/calculations/${calculation.id}`,
                                );
                            }
                        }}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>

                <TotalsCard calculationId={calculation.id} totals={totals} />

                <Separator />

                <Heading
                    variant="small"
                    title="Räume"
                    description="Je Raum: Form, Maße, Belag — Türbreiten entfallen bei Sockel und Fugen; Fensterflächen zählen voll mit (Ausarbeiten ist Mehraufwand)"
                />

                <div className="grid max-w-4xl gap-2">
                    {rooms.map((room) => (
                        <RoomRow
                            key={room.id}
                            calculationId={calculation.id}
                            room={room}
                            editing={editRoom?.id === room.id}
                            onEdit={() =>
                                setEditRoom(
                                    editRoom?.id === room.id ? null : room,
                                )
                            }
                        />
                    ))}
                    {rooms.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Noch keine Räume — unten den ersten Raum anlegen
                            oder eine CSV einspielen.
                        </p>
                    )}
                </div>

                <RoomForm
                    key={editRoom?.id ?? 'new'}
                    calculationId={calculation.id}
                    room={editRoom}
                    shapes={shapes}
                    materials={materials}
                    onDone={() => setEditRoom(null)}
                />

                <Separator />

                <CsvImport calculationId={calculation.id} />

                <Separator />

                <SettingsForm calculation={calculation} projects={projects} />
            </div>
        </>
    );
}

function TotalsCard({
    calculationId,
    totals,
}: {
    calculationId: number;
    totals: Quantities;
}) {
    const quantityItems = [
        { label: 'Bodenfläche', value: totals.area, unit: 'm²' },
        {
            label: 'Parkett (inkl. Verschnitt)',
            value: totals.parquet_area,
            unit: 'm²',
        },
        // Fliesen ohne und mit Verschnitt untereinander — verlegt wird
        // ohne, bestellt mit.
        {
            label: 'Bodenfliesen',
            value: totals.floor_tile_area_raw,
            withWaste: totals.floor_tile_area,
            unit: 'm²',
        },
        {
            label: 'Wandfliesen',
            value: totals.wall_tile_area_raw,
            withWaste: totals.wall_tile_area,
            unit: 'm²',
        },
        { label: 'Silikonfugen', value: totals.silicone, unit: 'lfm' },
        // Sockelleisten getrennt — Parkett- und Fliesensockel sind
        // unterschiedliche Produkte.
        {
            label: 'Sockelleisten Parkett',
            value: totals.skirting_parquet,
            unit: 'lfm',
        },
        {
            label: 'Sockelleisten Fliesen',
            value: totals.skirting_tiles,
            unit: 'lfm',
        },
        { label: 'Malerfläche', value: totals.painting_area, unit: 'm²' },
    ].filter((item) => item.value > 0);

    return (
        <div className="grid max-w-4xl gap-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                {quantityItems.map((item) => (
                    <div key={item.label}>
                        <div className="text-xs text-muted-foreground">
                            {item.label}
                        </div>
                        {'withWaste' in item && item.withWaste !== undefined ? (
                            <>
                                <div className="font-medium">
                                    {item.value.toLocaleString('de-AT')}{' '}
                                    {item.unit}{' '}
                                    <span className="text-xs font-normal text-muted-foreground">
                                        ohne Verschnitt
                                    </span>
                                </div>
                                <div className="font-medium">
                                    {item.withWaste.toLocaleString('de-AT')}{' '}
                                    {item.unit}{' '}
                                    <span className="text-xs font-normal text-muted-foreground">
                                        mit Verschnitt
                                    </span>
                                </div>
                            </>
                        ) : (
                            <div className="font-medium">
                                {item.value.toLocaleString('de-AT')} {item.unit}
                            </div>
                        )}
                    </div>
                ))}
                <div className="ml-auto text-right">
                    <div className="text-xs text-muted-foreground">
                        Summe netto
                    </div>
                    <div className="text-2xl font-semibold">
                        {formatEUR(totals.cost)}
                    </div>
                </div>
            </div>
            {totals.cost > 0 && (
                <div>
                    <Button asChild variant="outline" size="sm">
                        <Link
                            href={`/offers/create?calculation=${calculationId}`}
                        >
                            <FileText className="size-4" />
                            Als Angebot übernehmen
                        </Link>
                    </Button>
                </div>
            )}
        </div>
    );
}

function RoomRow({
    calculationId,
    room,
    editing,
    onEdit,
}: {
    calculationId: number;
    room: Room;
    editing: boolean;
    onEdit: () => void;
}) {
    const q = room.quantities;
    const parts = [
        `${q.area.toLocaleString('de-AT')} m² Boden`,
        q.wall_tile_area > 0
            ? `${q.wall_tile_area.toLocaleString('de-AT')} m² Wandfliesen`
            : null,
        q.silicone > 0
            ? `${q.silicone.toLocaleString('de-AT')} lfm Silikon`
            : null,
        q.skirting > 0
            ? `${q.skirting.toLocaleString('de-AT')} lfm Sockel`
            : null,
        `${q.painting_area.toLocaleString('de-AT')} m² Maler`,
    ].filter(Boolean);

    return (
        <div
            className={`flex items-center gap-3 rounded-lg border p-3 dark:border-sidebar-border ${
                editing ? 'border-primary' : 'border-sidebar-border/70'
            }`}
        >
            <div className="flex-1">
                <div className="flex items-center gap-2 font-medium">
                    {room.name}
                    {room.estimated && (
                        <Badge variant="secondary" title="Maße geschätzt">
                            ≈ geschätzt
                        </Badge>
                    )}
                    <Badge variant="outline">{room.material_label}</Badge>
                    <span className="text-xs text-muted-foreground">
                        {room.shape_label}
                    </span>
                </div>
                <div className="text-sm text-muted-foreground">
                    {parts.join(' · ')}
                </div>
            </div>
            <div className="font-medium">{formatEUR(q.cost)}</div>
            <Button
                variant="ghost"
                size="icon"
                aria-label={`${room.name} bearbeiten`}
                onClick={onEdit}
            >
                <Pencil className="size-4" />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                aria-label={`${room.name} löschen`}
                onClick={() =>
                    router.delete(
                        `/calculations/${calculationId}/rooms/${room.id}`,
                        { preserveScroll: true },
                    )
                }
            >
                <Trash2 className="size-4" />
            </Button>
        </div>
    );
}

function RoomForm({
    calculationId,
    room,
    shapes,
    materials,
    onDone,
}: {
    calculationId: number;
    room: Room | null;
    shapes: OptionValue[];
    materials: OptionValue[];
    onDone: () => void;
}) {
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        name: room?.name ?? '',
        shape: room?.shape ?? 'rectangle',
        material: room?.material ?? 'parquet',
        length: room?.length ?? '',
        width: room?.width ?? '',
        height: room?.height ?? '2.5',
        length2: room?.length2 ?? '',
        width2: room?.width2 ?? '',
        depth: room?.depth ?? '',
        area_manual: room?.area_manual ?? '',
        perimeter_manual: room?.perimeter_manual ?? '',
        edges: room ? String(room.edges) : '0',
        door_width: room?.door_width ?? '0',
        estimated: room?.estimated ?? false,
    });

    const shape = data.shape;
    const showLength = shape !== 'manual';
    const showWidth = ['rectangle', 'l_shape', 'trapezoid'].includes(shape);
    const showCutout = shape === 'l_shape';
    const showDepth = ['trapezoid', 'triangle'].includes(shape);
    const showManual = shape === 'manual';
    const isWallTiles = data.material === 'wall_tiles';

    return (
        <form
            className="grid max-w-4xl gap-4 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            onSubmit={(event) => {
                event.preventDefault();

                const options = {
                    preserveScroll: true,
                    onSuccess: () => {
                        reset();
                        onDone();
                    },
                };

                if (room) {
                    patch(
                        `/calculations/${calculationId}/rooms/${room.id}`,
                        options,
                    );
                } else {
                    post(`/calculations/${calculationId}/rooms`, options);
                }
            }}
        >
            <Heading
                variant="small"
                title={room ? `Raum bearbeiten: ${room.name}` : 'Neuer Raum'}
                description="Länge des Trapezes = lange Seite, Breite = kurze Seite; beim Dreieck zählen die beiden Katheten"
            />
            <fieldset className="grid gap-4" disabled={processing}>
                <div className="grid grid-cols-3 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="room-name">Raum</Label>
                        <Input
                            id="room-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="z. B. Wohnzimmer"
                            required
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="room-shape">Form</Label>
                        <Select
                            value={data.shape}
                            onValueChange={(value) => setData('shape', value)}
                        >
                            <SelectTrigger id="room-shape">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {shapes.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.shape} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="room-material">Belag</Label>
                        <Select
                            value={data.material}
                            onValueChange={(value) =>
                                setData('material', value)
                            }
                        >
                            <SelectTrigger id="room-material">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {materials.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.material} />
                    </div>
                </div>

                <div className="grid grid-cols-4 gap-4">
                    {showLength && (
                        <NumberField
                            id="room-length"
                            label={
                                shape === 'triangle'
                                    ? 'Kathete a (m)'
                                    : 'Länge (m)'
                            }
                            value={data.length}
                            onChange={(v) => setData('length', v)}
                            error={errors.length}
                        />
                    )}
                    {showWidth && (
                        <NumberField
                            id="room-width"
                            label="Breite (m)"
                            value={data.width}
                            onChange={(v) => setData('width', v)}
                            error={errors.width}
                        />
                    )}
                    {showDepth && (
                        <NumberField
                            id="room-depth"
                            label={
                                shape === 'triangle'
                                    ? 'Kathete b (m)'
                                    : 'Tiefe (m)'
                            }
                            value={data.depth}
                            onChange={(v) => setData('depth', v)}
                            error={errors.depth}
                        />
                    )}
                    <NumberField
                        id="room-height"
                        label="Raumhöhe (m)"
                        value={data.height}
                        onChange={(v) => setData('height', v)}
                        error={errors.height}
                    />
                </div>

                {showCutout && (
                    <div className="grid grid-cols-4 gap-4">
                        <NumberField
                            id="room-length2"
                            label="Ausschnitt-Länge (m)"
                            value={data.length2}
                            onChange={(v) => setData('length2', v)}
                            error={errors.length2}
                        />
                        <NumberField
                            id="room-width2"
                            label="Ausschnitt-Breite (m)"
                            value={data.width2}
                            onChange={(v) => setData('width2', v)}
                            error={errors.width2}
                        />
                    </div>
                )}

                {showManual && (
                    <div className="grid grid-cols-4 gap-4">
                        <NumberField
                            id="room-area-manual"
                            label="Fläche (m²)"
                            value={data.area_manual}
                            onChange={(v) => setData('area_manual', v)}
                            error={errors.area_manual}
                        />
                        <NumberField
                            id="room-perimeter-manual"
                            label="Umfang (lfm)"
                            value={data.perimeter_manual}
                            onChange={(v) => setData('perimeter_manual', v)}
                            error={errors.perimeter_manual}
                        />
                    </div>
                )}

                <div className="grid grid-cols-4 gap-4">
                    <NumberField
                        id="room-door-width"
                        label="Türbreiten gesamt (m)"
                        value={data.door_width}
                        onChange={(v) => setData('door_width', v)}
                        error={errors.door_width}
                        hint="entfällt bei Sockel und Fugen"
                    />
                    {isWallTiles && (
                        <NumberField
                            id="room-edges"
                            label="Außenkanten (Stück)"
                            value={data.edges}
                            onChange={(v) => setData('edges', v)}
                            error={errors.edges}
                            hint="Silikon je Kante bis Fliesenhöhe"
                            step="1"
                        />
                    )}
                </div>

                <div className="flex items-center gap-4">
                    <Label className="flex items-center gap-2 text-sm font-normal">
                        <Checkbox
                            checked={data.estimated}
                            onCheckedChange={(checked) =>
                                setData('estimated', checked === true)
                            }
                        />
                        Maße geschätzt (wird mit ≈ markiert)
                    </Label>
                    <div className="ml-auto flex gap-2">
                        {room && (
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
                            {room ? 'Raum speichern' : 'Raum hinzufügen'}
                        </Button>
                    </div>
                </div>
            </fieldset>
        </form>
    );
}

function NumberField({
    id,
    label,
    value,
    onChange,
    error,
    hint,
    step = '0.01',
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    hint?: string;
    step?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                step={step}
                min={0}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function CsvImport({ calculationId }: { calculationId: number }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm({
        csv: '',
    });

    return (
        <form
            className="grid max-w-4xl gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/calculations/${calculationId}/import`, {
                    preserveScroll: true,
                    onSuccess: () => reset('csv'),
                });
            }}
        >
            <Heading
                variant="small"
                title="Räume aus CSV/TXT importieren"
                description="Spalten wie Name, Länge, Breite, Höhe, Material (Parkett/Bodenfliesen/Wandfliesen), Kanten, Tür — Trenner , ; oder Tab"
            />
            <input
                ref={fileInput}
                type="file"
                accept=".csv,.txt"
                className="hidden"
                aria-hidden
                tabIndex={-1}
                onChange={(event) => {
                    const file = event.target.files?.[0];

                    if (file) {
                        void file.text().then((text) => setData('csv', text));
                    }

                    event.target.value = '';
                }}
            />
            <Textarea
                value={data.csv}
                onChange={(event) => setData('csv', event.target.value)}
                placeholder={
                    'Name;Länge;Breite;Höhe;Material\nWohnzimmer;5,2;4,1;2,5;Parkett\nBad;2,4;2,0;2,5;Wandfliesen'
                }
                rows={4}
            />
            <InputError message={errors.csv} />
            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => fileInput.current?.click()}
                >
                    Datei wählen
                </Button>
                <Button
                    type="submit"
                    disabled={processing || data.csv.trim() === ''}
                >
                    <Upload className="size-4" />
                    Importieren
                </Button>
            </div>
        </form>
    );
}

function SettingsForm({
    calculation,
    projects,
}: {
    calculation: CalculationData;
    projects: ProjectOption[];
}) {
    const { data, setData, patch, processing, errors, transform } = useForm({
        name: calculation.name,
        project_id: calculation.project_id
            ? String(calculation.project_id)
            : 'none',
        waste_percent: calculation.waste_percent,
        wall_tile_height: calculation.wall_tile_height,
        price_parquet: calculation.price_parquet,
        price_floor_tiles: calculation.price_floor_tiles,
        price_wall_tiles: calculation.price_wall_tiles,
        price_silicone: calculation.price_silicone,
        price_skirting: calculation.price_skirting,
        price_painting: calculation.price_painting,
        notes: calculation.notes ?? '',
        lock_version: calculation.lock_version,
    });

    const priceFields: {
        key:
            | 'price_parquet'
            | 'price_floor_tiles'
            | 'price_wall_tiles'
            | 'price_silicone'
            | 'price_skirting'
            | 'price_painting';
        label: string;
    }[] = [
        { key: 'price_parquet', label: 'Parkett €/m²' },
        { key: 'price_floor_tiles', label: 'Bodenfliesen €/m²' },
        { key: 'price_wall_tiles', label: 'Wandfliesen €/m²' },
        { key: 'price_silicone', label: 'Silikon €/lfm' },
        { key: 'price_skirting', label: 'Sockel €/lfm' },
        { key: 'price_painting', label: 'Maler €/m²' },
    ];

    return (
        <form
            className="grid max-w-4xl gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                transform((values) => ({
                    ...values,
                    project_id:
                        values.project_id === 'none'
                            ? null
                            : Number(values.project_id),
                }));
                patch(`/calculations/${calculation.id}`, {
                    preserveScroll: true,
                });
            }}
        >
            <Heading
                variant="small"
                title="Einstellungen & Preise"
                description="Preise gelten je Kalkulation — die Summe rechnet sofort mit"
            />
            <fieldset className="grid gap-4" disabled={processing}>
                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="calc-name">Name</Label>
                        <Input
                            id="calc-name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError
                            message={errors.name ?? errors.lock_version}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="calc-project">Projekt (optional)</Label>
                        <Select
                            value={data.project_id}
                            onValueChange={(value) =>
                                setData('project_id', value)
                            }
                        >
                            <SelectTrigger id="calc-project">
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
                </div>

                <div className="grid grid-cols-4 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="calc-waste">Verschnitt (%)</Label>
                        <Input
                            id="calc-waste"
                            type="number"
                            step="0.5"
                            min={0}
                            max={100}
                            value={data.waste_percent}
                            onChange={(e) =>
                                setData('waste_percent', e.target.value)
                            }
                        />
                        <InputError message={errors.waste_percent} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="calc-tile-height">
                            Wandfliesen-Höhe (m)
                        </Label>
                        <Input
                            id="calc-tile-height"
                            type="number"
                            step="0.05"
                            min={0.5}
                            value={data.wall_tile_height}
                            onChange={(e) =>
                                setData('wall_tile_height', e.target.value)
                            }
                        />
                        <InputError message={errors.wall_tile_height} />
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-4 sm:grid-cols-6">
                    {priceFields.map((field) => (
                        <div key={field.key} className="grid gap-2">
                            <Label htmlFor={`calc-${field.key}`}>
                                {field.label}
                            </Label>
                            <Input
                                id={`calc-${field.key}`}
                                type="number"
                                step="0.01"
                                min={0}
                                value={data[field.key]}
                                onChange={(e) =>
                                    setData(field.key, e.target.value)
                                }
                            />
                            <InputError message={errors[field.key]} />
                        </div>
                    ))}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="calc-notes">Notizen</Label>
                    <Textarea
                        id="calc-notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                    <InputError message={errors.notes} />
                </div>

                <Button type="submit" className="w-fit">
                    Einstellungen speichern
                </Button>
            </fieldset>
        </form>
    );
}

CalculationsShow.layout = ({ calculation }: Props) => ({
    breadcrumbs: [
        { title: 'Baukalkulation', href: '/calculations' },
        {
            title: calculation.name,
            href: `/calculations/${calculation.id}`,
        },
    ],
});
