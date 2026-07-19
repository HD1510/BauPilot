import { Head, router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, KeyRound, UserPlus } from 'lucide-react';
import { DocumentsSection } from '@/components/documents-section';
import type { DocumentItem } from '@/components/documents-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { EmployeeForm } from '@/components/master-data/employee-form';
import type {
    EmployeeFormValues,
    UserOption,
} from '@/components/master-data/employee-form';
import { OvertimeSection } from '@/components/master-data/overtime-section';
import type { OvertimeData } from '@/components/master-data/overtime-section';
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

type RoleOption = { value: string; label: string };

type Account = {
    id: number;
    name: string;
    username: string | null;
    email: string | null;
};

type Props = {
    employee: EmployeeFormValues & { id: number; active: boolean };
    users: UserOption[];
    canWrite: boolean;
    overtime: OvertimeData | null;
    account: Account | null;
    canManageAccount: boolean;
    roles: RoleOption[];
    documents: DocumentItem[] | null;
};

export default function EmployeesEdit({
    employee,
    users,
    canWrite,
    overtime,
    account,
    canManageAccount,
    roles,
    documents,
}: Props) {
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

                {/* key: Nach Konto-Anlage ändert sich user_id — das
                    Formular liest die Verknüpfung sonst nicht neu ein
                    und würde sie beim Speichern wieder lösen. */}
                <EmployeeForm
                    key={employee.user_id ?? 'none'}
                    action={`/employees/${employee.id}`}
                    method="patch"
                    employee={employee}
                    users={users}
                    submitLabel="Änderungen speichern"
                    disabled={!canWrite || !employee.active}
                />

                {canManageAccount && (
                    <>
                        <Separator />
                        <AccountSection
                            employeeId={employee.id}
                            account={account}
                            roles={roles}
                        />
                    </>
                )}

                {documents && (
                    <>
                        <Separator />
                        <DocumentsSection
                            documentableType="employee"
                            documentableId={employee.id}
                            documents={documents}
                            canWrite={canWrite}
                            defaultCategory="contract"
                            categories={[
                                { value: 'contract', label: 'Arbeitsvertrag' },
                                {
                                    value: 'certificate',
                                    label: 'Ausbildungsnachweis',
                                },
                                { value: 'photo', label: 'Foto' },
                                { value: 'other', label: 'Sonstiges' },
                            ]}
                        />
                    </>
                )}

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

/**
 * Zugang des Mitarbeiters: Konto mit Benutzername und Startpasswort
 * anlegen bzw. ein neues Startpasswort setzen — die Zugangsdaten werden
 * persönlich weitergegeben, eine E-Mail ist nicht nötig.
 */
function AccountSection({
    employeeId,
    account,
    roles,
}: {
    employeeId: number;
    account: Account | null;
    roles: RoleOption[];
}) {
    return (
        <div className="grid max-w-xl gap-3">
            <Heading
                variant="small"
                title="Zugang"
                description={
                    account
                        ? 'Dieser Mitarbeiter kann sich mit seinem Benutzernamen anmelden.'
                        : 'Benutzerkonto mit Benutzername und Startpasswort anlegen — Zugangsdaten persönlich weitergeben; das Passwort kann die Person danach selbst ändern.'
                }
            />
            {account ? (
                <ExistingAccount employeeId={employeeId} account={account} />
            ) : (
                <CreateAccountForm employeeId={employeeId} roles={roles} />
            )}
        </div>
    );
}

function ExistingAccount({
    employeeId,
    account,
}: {
    employeeId: number;
    account: Account;
}) {
    const { data, setData, patch, processing, errors, reset } = useForm({
        password: '',
    });

    return (
        <div className="grid gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
            <div>
                <div className="font-medium">
                    {account.username ?? account.email}
                </div>
                <div className="text-sm text-muted-foreground">
                    {account.username
                        ? 'Anmeldung mit Benutzername'
                        : `Anmeldung mit E-Mail-Adresse (${account.email})`}
                </div>
            </div>
            <form
                className="flex items-end gap-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    patch(`/employees/${employeeId}/account/password`, {
                        preserveScroll: true,
                        onSuccess: () => reset('password'),
                    });
                }}
            >
                <div className="grid flex-1 gap-2">
                    <Label htmlFor="account-new-password">
                        Neues Startpasswort (mind. 8 Zeichen)
                    </Label>
                    <Input
                        id="account-new-password"
                        type="text"
                        autoComplete="off"
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        required
                    />
                    <InputError message={errors.password} />
                </div>
                <Button type="submit" variant="outline" disabled={processing}>
                    <KeyRound className="size-4" />
                    Passwort setzen
                </Button>
            </form>
        </div>
    );
}

function CreateAccountForm({
    employeeId,
    roles,
}: {
    employeeId: number;
    roles: RoleOption[];
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        password: '',
        role: 'site',
    });

    return (
        <form
            className="grid gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/employees/${employeeId}/account`, {
                    preserveScroll: true,
                    onSuccess: () => reset('username', 'password'),
                });
            }}
        >
            <div className="grid grid-cols-2 gap-3">
                <div className="grid gap-2">
                    <Label htmlFor="account-username">Benutzername</Label>
                    <Input
                        id="account-username"
                        value={data.username}
                        onChange={(event) =>
                            setData('username', event.target.value)
                        }
                        placeholder="z. B. m.huber"
                        required
                    />
                    <InputError message={errors.username} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="account-password">
                        Startpasswort (mind. 8 Zeichen)
                    </Label>
                    <Input
                        id="account-password"
                        type="text"
                        autoComplete="off"
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        required
                    />
                    <InputError message={errors.password ?? errors.role} />
                </div>
            </div>
            <div className="flex items-end gap-3">
                <Select
                    value={data.role}
                    onValueChange={(role) => setData('role', role)}
                >
                    <SelectTrigger
                        className="w-40"
                        aria-label="Rolle des neuen Kontos"
                    >
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
                    <UserPlus className="size-4" />
                    Konto anlegen
                </Button>
            </div>
        </form>
    );
}

EmployeesEdit.layout = ({ employee }: Props) => ({
    breadcrumbs: [
        { title: 'Mitarbeiter', href: '/employees' },
        { title: employee.name, href: `/employees/${employee.id}/edit` },
    ],
});
