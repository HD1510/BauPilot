import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Archive, ArchiveRestore, Trash2 } from 'lucide-react';
import { CompanyForm  } from '@/components/company-form';
import type {CompanyFormValues} from '@/components/company-form';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { CompanyRole } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string;
    role: CompanyRole;
};

type RoleOption = { value: CompanyRole; label: string };

type Props = {
    company: CompanyFormValues & { id: number; archived_at: string | null };
    members: Member[];
    roles: RoleOption[];
};

export default function CompaniesEdit({ company, members, roles }: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={`Firma: ${company.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={company.name}
                        description="Stammdaten, Benutzer und Rollen dieser Firma"
                    />
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.patch(`/companies/${company.id}/archive`)
                        }
                    >
                        {company.archived_at ? (
                            <>
                                <ArchiveRestore className="size-4" />
                                Wieder aktivieren
                            </>
                        ) : (
                            <>
                                <Archive className="size-4" />
                                Archivieren
                            </>
                        )}
                    </Button>
                </div>

                {company.archived_at && (
                    <Badge variant="secondary" className="w-fit">
                        Diese Firma ist archiviert.
                    </Badge>
                )}

                <CompanyForm
                    action={`/companies/${company.id}`}
                    method="patch"
                    company={company}
                    submitLabel="Änderungen speichern"
                />

                <Separator />

                <Heading
                    variant="small"
                    title="Benutzer und Rollen"
                    description="Wer in dieser Firma arbeitet und was die Person sehen darf. Die Rolle Baustelle sieht keine Finanzdaten."
                />

                <div className="grid max-w-xl gap-3">
                    {members.map((member) => (
                        <div
                            key={member.id}
                            className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                        >
                            <div className="flex-1">
                                <div className="font-medium">
                                    {member.name}
                                    {member.id === auth.user.id && (
                                        <span className="ml-2 text-xs text-muted-foreground">
                                            (Sie)
                                        </span>
                                    )}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {member.email}
                                </div>
                            </div>
                            <Select
                                defaultValue={member.role}
                                onValueChange={(role) =>
                                    router.patch(
                                        `/companies/${company.id}/members/${member.id}`,
                                        { role },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="w-40"
                                    aria-label={`Rolle von ${member.name}`}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem
                                            key={role.value}
                                            value={role.value}
                                        >
                                            {role.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`${member.name} entfernen`}
                                onClick={() =>
                                    router.delete(
                                        `/companies/${company.id}/members/${member.id}`,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    ))}
                </div>

                <AddMemberForm companyId={company.id} roles={roles} />
            </div>
        </>
    );
}

function AddMemberForm({
    companyId,
    roles,
}: {
    companyId: number;
    roles: RoleOption[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        email: string;
        role: CompanyRole;
    }>({ email: '', role: 'office' });

    return (
        <form
            className="flex max-w-xl items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/companies/${companyId}/members`, {
                    preserveScroll: true,
                    onSuccess: () => reset('email'),
                });
            }}
        >
            <div className="grid flex-1 gap-2">
                <Label htmlFor="member-email">Benutzer hinzufügen</Label>
                <Input
                    id="member-email"
                    type="email"
                    placeholder="E-Mail-Adresse des Benutzerkontos"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    required
                />
                <InputError message={errors.email ?? errors.role} />
            </div>
            <Select
                value={data.role}
                onValueChange={(role) => setData('role', role as CompanyRole)}
            >
                <SelectTrigger className="w-40" aria-label="Rolle">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {roles.map((role) => (
                        <SelectItem key={role.value} value={role.value}>
                            {role.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Button type="submit" disabled={processing}>
                Hinzufügen
            </Button>
        </form>
    );
}

CompaniesEdit.layout = ({ company }: Props) => ({
    breadcrumbs: [
        { title: 'Firmen', href: '/companies' },
        { title: company.name, href: `/companies/${company.id}/edit` },
    ],
});
