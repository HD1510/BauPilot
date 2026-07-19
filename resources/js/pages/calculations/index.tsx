import { Head, Link, useForm } from '@inertiajs/react';
import { Calculator, Plus } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatEUR } from '@/lib/format';

type CalculationListItem = {
    id: number;
    name: string;
    rooms_count: number;
    project: string | null;
    total: number;
};

export default function CalculationsIndex({
    calculations,
}: {
    calculations: CalculationListItem[];
}) {
    return (
        <>
            <Head title="Baukalkulation" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Baukalkulation"
                    description="Räume erfassen — Gewerke-Mengen und Angebotssumme rechnet BauPilot"
                />

                <CreateForm />

                <div className="grid max-w-3xl gap-3">
                    {calculations.map((calculation) => (
                        <Link
                            key={calculation.id}
                            href={`/calculations/${calculation.id}`}
                            className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 hover:bg-accent/50 dark:border-sidebar-border"
                        >
                            <Calculator className="size-4 shrink-0 text-muted-foreground" />
                            <div className="flex-1">
                                <div className="flex items-center gap-2 font-medium">
                                    {calculation.name}
                                    {calculation.project && (
                                        <Badge variant="outline">
                                            {calculation.project}
                                        </Badge>
                                    )}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {calculation.rooms_count === 1
                                        ? '1 Raum'
                                        : `${calculation.rooms_count} Räume`}
                                </div>
                            </div>
                            <div className="font-medium">
                                {formatEUR(calculation.total)}
                            </div>
                        </Link>
                    ))}
                    {calculations.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Noch keine Kalkulation — legen Sie die erste an.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

function CreateForm() {
    const { data, setData, post, processing, errors } = useForm({ name: '' });

    return (
        <form
            className="flex max-w-3xl items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post('/calculations');
            }}
        >
            <div className="grid flex-1 gap-2">
                <Label htmlFor="calculation-name">Neue Kalkulation</Label>
                <Input
                    id="calculation-name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    placeholder="z. B. EFH Familie Huber — Bodenbeläge"
                    required
                />
                <InputError message={errors.name} />
            </div>
            <Button type="submit" disabled={processing}>
                <Plus className="size-4" />
                Anlegen
            </Button>
        </form>
    );
}

CalculationsIndex.layout = {
    breadcrumbs: [{ title: 'Baukalkulation', href: '/calculations' }],
};
