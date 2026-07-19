import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { EmployeeForm } from '@/components/master-data/employee-form';
import type { UserOption } from '@/components/master-data/employee-form';

export default function EmployeesCreate({ users }: { users: UserOption[] }) {
    return (
        <>
            <Head title="Neuer Mitarbeiter" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neuer Mitarbeiter"
                    description="Mitarbeiter können später optional mit einem Benutzerkonto verknüpft werden"
                />
                <EmployeeForm
                    action="/employees"
                    method="post"
                    users={users}
                    submitLabel="Mitarbeiter anlegen"
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
