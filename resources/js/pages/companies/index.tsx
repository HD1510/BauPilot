import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { CompanyBadge } from '@/components/company-badge';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { companyRoleLabels  } from '@/types';
import type {CompanyRole} from '@/types';

type CompanyListItem = {
    id: number;
    name: string;
    short_code: string;
    color: string;
    legal_form: string | null;
    archived_at: string | null;
    role: CompanyRole;
};

export default function CompaniesIndex({
    companies,
}: {
    companies: CompanyListItem[];
}) {
    return (
        <>
            <Head title="Firmen" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Firmen"
                        description="Mandanten, in denen Sie arbeiten"
                    />
                    <Button asChild>
                        <Link href="/companies/create">
                            <Plus className="size-4" />
                            Neue Firma
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-3">
                    {companies.map((company) => (
                        <div
                            key={company.id}
                            className="flex items-center gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                        >
                            <CompanyBadge
                                shortCode={company.short_code}
                                color={company.color}
                            />
                            <div className="flex-1">
                                <div className="flex items-center gap-2 font-medium">
                                    {company.name}
                                    {company.archived_at && (
                                        <Badge variant="secondary">
                                            archiviert
                                        </Badge>
                                    )}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {company.legal_form ?? '—'} ·{' '}
                                    {companyRoleLabels[company.role]}
                                </div>
                            </div>
                            {company.role === 'admin' && (
                                <Button variant="outline" asChild>
                                    <Link href={`/companies/${company.id}/edit`}>
                                        Bearbeiten
                                    </Link>
                                </Button>
                            )}
                        </div>
                    ))}

                    {companies.length === 0 && (
                        <p className="text-muted-foreground">
                            Sie sind noch keiner Firma zugeordnet. Legen Sie
                            eine Firma an oder lassen Sie sich von einem
                            Administrator hinzufügen.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

CompaniesIndex.layout = {
    breadcrumbs: [{ title: 'Firmen', href: '/companies' }],
};
