import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { EmployeeForm } from '@/components/master-data/employee-form';
import type {
    RoleOption,
    UserOption,
} from '@/components/master-data/employee-form';

export default function EmployeesCreate({
    users,
    accountRoles,
}: {
    users: UserOption[];
    accountRoles: RoleOption[] | null;
}) {
    return (
        <>
            <Head title="Neuer Mitarbeiter" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neuer Mitarbeiter"
                    description="Auf Wunsch gleich mit Benutzerkonto — Anmeldung per Benutzername"
                />
                <EmployeeForm
                    action="/employees"
                    method="post"
                    users={users}
                    submitLabel="Mitarbeiter anlegen"
                    accountRoles={accountRoles}
                />
            </div>
        </>
    );
}

EmployeesCreate.layout = {
    breadcrumbs: [
        { title: 'Mitarbeiter', href: '/employees' },
        { title: 'Neuer Mitarbeiter', href: '/employees/create' },
    ],
};
