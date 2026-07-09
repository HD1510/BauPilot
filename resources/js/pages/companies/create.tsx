import { Head } from '@inertiajs/react';
import { CompanyForm } from '@/components/company-form';
import Heading from '@/components/heading';

export default function CompaniesCreate() {
    return (
        <>
            <Head title="Neue Firma" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neue Firma"
                    description="Sie werden automatisch Administrator der neuen Firma"
                />
                <CompanyForm
                    action="/companies"
                    method="post"
                    submitLabel="Firma anlegen"
                />
            </div>
        </>
    );
}

CompaniesCreate.layout = {
    breadcrumbs: [
        { title: 'Firmen', href: '/companies' },
        { title: 'Neue Firma', href: '/companies/create' },
    ],
};
