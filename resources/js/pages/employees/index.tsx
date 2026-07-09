import { Head, Link, usePage } from '@inertiajs/react';
import {
    EmptyState,
    IndexShell
    
} from '@/components/master-data/index-shell';
import type {IndexFilters} from '@/components/master-data/index-shell';
import { Badge } from '@/components/ui/badge';

type EmployeeListItem = {
    id: number;
    name: string;
    overtime_rate: string | null;
    calc_hourly_rate: string | null;
    user: { id: number; name: string; email: string } | null;
    archived: boolean;
};

export default function EmployeesIndex({
    employees,
    filters,
}: {
    employees: EmployeeListItem[];
    filters: IndexFilters;
}) {
    const { tenancy } = usePage().props;

    return (
        <>
            <Head title="Mitarbeiter" />
            <IndexShell
                title="Mitarbeiter"
                description="Mitarbeiterstamm — auch ohne eigenes Benutzerkonto"
                basePath="/employees"
                createLabel="Neuer Mitarbeiter"
                filters={filters}
            >
                {employees.map((employee) => (
                    <Link
                        key={employee.id}
                        href={`/employees/${employee.id}/edit`}
                        className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 hover:bg-accent/50 dark:border-sidebar-border"
                    >
                        <div className="flex-1">
                            <div className="flex items-center gap-2 font-medium">
                                {employee.name}
                                {employee.archived && (
                                    <Badge variant="secondary">
                                        archiviert
                                    </Badge>
                                )}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {employee.user
                                    ? `Benutzerkonto: ${employee.user.email}`
                                    : 'Kein Benutzerkonto'}
                            </div>
                        </div>
                        {tenancy.canViewFinancials && (
                            <div className="text-right text-sm text-muted-foreground">
                                {employee.overtime_rate && (
                                    <div>
                                        Überstunden: {employee.overtime_rate} €
                                    </div>
                                )}
                                {employee.calc_hourly_rate && (
                                    <div>
                                        Kalk.: {employee.calc_hourly_rate} €/h
                                    </div>
                                )}
                            </div>
                        )}
                    </Link>
                ))}
                {employees.length === 0 && (
                    <EmptyState archived={filters.archived} />
                )}
            </IndexShell>
        </>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [{ title: 'Mitarbeiter', href: '/employees' }],
};
