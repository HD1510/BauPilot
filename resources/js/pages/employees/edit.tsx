import { Head, router } from '@inertiajs/react';
import { Archive, ArchiveRestore } from 'lucide-react';
import Heading from '@/components/heading';
import {
    EmployeeForm
    
    
} from '@/components/master-data/employee-form';
import type {EmployeeFormValues, UserOption} from '@/components/master-data/employee-form';
import { OvertimeSection } from '@/components/master-data/overtime-section';
import type { OvertimeData } from '@/components/master-data/overtime-section';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';

type Props = {
    employee: EmployeeFormValues & { id: number; active: boolean };
    users: UserOption[];
    canWrite: boolean;
    overtime: OvertimeData | null;
};

export default function EmployeesEdit({ employee, users, canWrite, overtime }: Props) {
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

                {overtime && (
                    <>
                        <Separator />
                        <OvertimeSection
                            employeeId={employee.id}
                            overtime={overtime}
                            canWrite={canWrite}
                        />
                    </>
                )}
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
