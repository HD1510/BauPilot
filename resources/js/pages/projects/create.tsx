import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    ProjectForm
    
    
} from '@/components/sales/project-form';
import type {Option, StatusOption} from '@/components/sales/project-form';

export default function ProjectsCreate({
    customers,
    users,
    statuses,
}: {
    customers: Option[];
    users: Option[];
    statuses: StatusOption[];
}) {
    return (
        <>
            <Head title="Neues Projekt" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neues Projekt"
                    description="Projekte entstehen meist aus angenommenen Angeboten — oder direkt hier"
                />
                <ProjectForm
                    action="/projects"
                    method="post"
                    customers={customers}
                    users={users}
                    statuses={statuses}
                    submitLabel="Projekt anlegen"
                />
            </div>
        </>
    );
}

ProjectsCreate.layout = {
    breadcrumbs: [
        { title: 'Projekte', href: '/projects' },
        { title: 'Neues Projekt', href: '/projects/create' },
    ],
};
