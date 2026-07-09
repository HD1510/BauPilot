import { Head, router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    CustomerForm
    
} from '@/components/master-data/customer-form';
import type {CustomerFormValues} from '@/components/master-data/customer-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

type Contact = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
};

type Props = {
    customer: CustomerFormValues & { id: number; archived: boolean };
    contacts: Contact[];
    canWrite: boolean;
};

export default function CustomersEdit({ customer, contacts, canWrite }: Props) {
    return (
        <>
            <Head title={`Kunde: ${customer.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={customer.name}
                        description="Kundenstammblatt mit Ansprechpartnern"
                    />
                    {canWrite &&
                        (customer.archived ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        `/customers/${customer.id}/restore`,
                                    )
                                }
                            >
                                <ArchiveRestore className="size-4" />
                                Wieder aktivieren
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        `/customers/${customer.id}/archive`,
                                    )
                                }
                            >
                                <Archive className="size-4" />
                                Archivieren
                            </Button>
                        ))}
                </div>

                {customer.archived && (
                    <Badge variant="secondary" className="w-fit">
                        Dieser Kunde ist archiviert.
                    </Badge>
                )}

                <CustomerForm
                    action={`/customers/${customer.id}`}
                    method="patch"
                    customer={customer}
                    submitLabel="Änderungen speichern"
                    disabled={!canWrite || customer.archived}
                />

                <Separator />

                <Heading
                    variant="small"
                    title="Ansprechpartner"
                    description="Kontaktpersonen dieses Kunden"
                />

                <div className="grid max-w-xl gap-2">
                    {contacts.map((contact) => (
                        <div
                            key={contact.id}
                            className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                        >
                            <div className="flex-1">
                                <div className="font-medium">{contact.name}</div>
                                <div className="text-sm text-muted-foreground">
                                    {[contact.phone, contact.email]
                                        .filter(Boolean)
                                        .join(' · ') || '—'}
                                </div>
                            </div>
                            {canWrite && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`${contact.name} entfernen`}
                                    onClick={() =>
                                        router.delete(
                                            `/customers/${customer.id}/contacts/${contact.id}`,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            )}
                        </div>
                    ))}
                    {contacts.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Noch keine Ansprechpartner.
                        </p>
                    )}
                </div>

                {canWrite && <AddContactForm customerId={customer.id} />}
            </div>
        </>
    );
}

function AddContactForm({ customerId }: { customerId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        phone: '',
        email: '',
    });

    return (
        <form
            className="flex max-w-xl items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/customers/${customerId}/contacts`, {
                    preserveScroll: true,
                    onSuccess: () => reset(),
                });
            }}
        >
            <div className="grid flex-1 gap-2">
                <Label htmlFor="contact-name">Name</Label>
                <Input
                    id="contact-name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError
                    message={errors.name ?? errors.phone ?? errors.email}
                />
            </div>
            <div className="grid flex-1 gap-2">
                <Label htmlFor="contact-phone">Telefon</Label>
                <Input
                    id="contact-phone"
                    value={data.phone}
                    onChange={(e) => setData('phone', e.target.value)}
                />
            </div>
            <div className="grid flex-1 gap-2">
                <Label htmlFor="contact-email">E-Mail</Label>
                <Input
                    id="contact-email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                />
            </div>
            <Button type="submit" disabled={processing}>
                Hinzufügen
            </Button>
        </form>
    );
}

CustomersEdit.layout = ({ customer }: Props) => ({
    breadcrumbs: [
        { title: 'Kunden', href: '/customers' },
        { title: customer.name, href: `/customers/${customer.id}/edit` },
    ],
});
