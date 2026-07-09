import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { VehicleForm } from '@/components/master-data/vehicle-form';

export default function VehiclesCreate() {
    return (
        <>
            <Head title="Neues Fahrzeug" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neues Fahrzeug"
                    description="Weitere Termine (Service, Eichung …) lassen sich nach dem Anlegen erfassen"
                />
                <VehicleForm
                    action="/vehicles"
                    method="post"
                    submitLabel="Fahrzeug anlegen"
                />
            </div>
        </>
    );
}

VehiclesCreate.layout = {
    breadcrumbs: [
        { title: 'Fahrzeuge', href: '/vehicles' },
        { title: 'Neues Fahrzeug', href: '/vehicles/create' },
    ],
};
