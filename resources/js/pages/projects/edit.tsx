import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    ProjectForm
    
    
    
} from '@/components/sales/project-form';
import type {Option, ProjectFormValues, StatusOption} from '@/components/sales/project-form';

type Props = {
    project: ProjectFormValues & { id: number };
    customers: Option[];
    users: Option[];
    statuses: StatusOption[];
};

export default function ProjectsEdit({
    project,
    customers,
    users,
    statuses,
}: Props) {
    return (
        <>
            <Head title={`Projekt bearbeiten: ${project.title}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title={project.title}
                    description="Projekt-Stammdaten bearbeiten"
                />
                <ProjectForm
                    action={`/projects/${project.id}`}
                    method="patch"
                    project={project}
                    customers={customers}
                    users={users}
                    statuses={statuses}
                    submitLabel="Änderungen speichern"
                />
            </div>
        </>
    );
}

ProjectsEdit.layout = ({ project }: Props) => ({
    breadcrumbs: [
        { title: 'Projekte', href: '/projects' },
        { title: project.title, href: `/projects/${project.id}` },
        { title: 'Bearbeiten', href: `/projects/${project.id}/edit` },
    ],
});
