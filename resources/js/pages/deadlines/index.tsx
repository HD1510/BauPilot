import { Head } from '@inertiajs/react';
import { DeadlineListRow } from '@/components/deadlines/deadline-list-row';
import type { DeadlineRow } from '@/components/deadlines/deadline-list-row';
import Heading from '@/components/heading';

export default function DeadlinesIndex({
    appointments,
    deadlines,
    horizonDays,
}: {
    appointments: DeadlineRow[];
    deadlines: DeadlineRow[];
    horizonDays: number;
}) {
    // Die Termin/Fristen-Trennung kommt vom Server (eine Regel, eine
    // Stelle); hier nur noch Überfälliges zuoberst.
    const overdue = deadlines.filter((deadline) => deadline.overdue);
    const upcoming = deadlines.filter((deadline) => !deadline.overdue);

    return (
        <>
            <Head title="Fristen" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Termine & Fristen"
                    description={`Die nächsten ${horizonDays} Tage — erledigen heißt immer, die Ursache zu bearbeiten`}
                />

                {overdue.length > 0 && (
                    <section className="grid max-w-3xl gap-2">
                        <Heading
                            variant="small"
                            title={`Überfällig (${overdue.length})`}
                            description=""
                        />
                        {overdue.map((deadline, index) => (
                            <DeadlineListRow
                                key={`o-${index}`}
                                row={deadline}
                            />
                        ))}
                    </section>
                )}

                <section className="grid max-w-3xl gap-2">
                    <Heading
                        variant="small"
                        title={`Termine (${appointments.length})`}
                        description="Projekttermine"
                    />
                    {appointments.map((deadline, index) => (
                        <DeadlineListRow key={`a-${index}`} row={deadline} />
                    ))}
                    {appointments.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Keine Termine anstehend.
                        </p>
                    )}
                </section>

                <section className="grid max-w-3xl gap-2">
                    <Heading
                        variant="small"
                        title={`Fristen (${upcoming.length})`}
                        description="Zahlungsziele, Skonto, Aufgaben, Fahrzeuge"
                    />
                    {upcoming.map((deadline, index) => (
                        <DeadlineListRow key={`u-${index}`} row={deadline} />
                    ))}
                    {upcoming.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Nichts anstehend. 🎉
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

DeadlinesIndex.layout = {
    breadcrumbs: [{ title: 'Fristen', href: '/deadlines' }],
};
