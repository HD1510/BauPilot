import { Head, router } from '@inertiajs/react';
import { Archive, ArchiveRestore } from 'lucide-react';
import Heading from '@/components/heading';
import {
    EmployeeForm
    
    
} from '@/components/master-data/employee-form';
import type {EmployeeFormValues, UserOption} from '@/components/master-data/employee-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    employee: EmployeeFormValues & { id: number; active: boolean };
    users: UserOption[];
    canWrite: boolean;
};

export default function EmployeesEdit({ employee, users, canWrite }: Props) {
    return (
        <>
            <Head title={`Mitarbeiter: ${employee.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={employee.name}
                        description="Mitarbeiterstammblatt"
                    />
                    {canWrite && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.patch(
                                    `/employees/${employee.id}/archive`,
                                )
                            }
                        >
                            {employee.active ? (
                                <>
                                    <Archive className="size-4" />
                                    Archivieren
                                </>
                            ) : (
                                <>
                                    <ArchiveRestore className="size-4" />
                                    Wieder aktivieren
                                </>
                            )}
                        </Button>
                    )}
                </div>

                {!employee.active && (
                    <Badge variant="secondary" className="w-fit">
                        Dieser Mitarbeiter ist archiviert.
                    </Badge>
                )}

                <EmployeeForm
                    action={`/employees/${employee.id}`}
                    method="patch"
                    employee={employee}
                    users={users}
                    submitLabel="Änderungen speichern"
                    disabled={!canWrite || !employee.active}
                />
            </div>
        </>
    );
}

EmployeesEdit.layout = ({ employee }: Props) => ({
    breadcrumbs: [
        { title: 'Mitarbeiter', href: '/employees' },
        { title: employee.name, href: `/employees/${employee.id}/edit` },
    ],
});
