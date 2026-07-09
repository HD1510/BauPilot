import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';

type ProjectRow = {
    id: number;
    title: string;
    customer: string | null;
    site_address: string | null;
    status: string;
    status_label: string;
    planned_finish_on: string | null;
};

export default function ProjectsIndex({
    projects,
    filters,
    statuses,
}: {
    projects: ProjectRow[];
    filters: { q: string; status: string };
    statuses: { value: string; label: string }[];
}) {
    return (
        <>
            <Head title="Projekte" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Projekte"
                        description="Die Drehscheibe: Termine, Nachträge, Belege und Zahlen je Bauvorhaben"
                    />
                    <Button asChild>
                        <Link href="/projects/create">
                            <Plus className="size-4" />
                            Neues Projekt
                        </Link>
                    </Button>
                </div>

                <Select
                    value={filters.status || 'all'}
                    onValueChange={(value) =>
                        router.get(
                            '/projects',
                            value === 'all' ? {} : { status: value },
                            { preserveState: true, replace: true },
                        )
                    }
                >
                    <SelectTrigger className="w-56" aria-label="Status-Filter">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Alle Status</SelectItem>
                        {statuses.map((status) => (
                            <SelectItem key={status.value} value={status.value}>
                                {status.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <div className="grid gap-2">
                    {projects.map((project) => (
                        <Link
                            key={project.id}
                            href={`/projects/${project.id}`}
                            className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 hover:bg-accent/50 dark:border-sidebar-border"
                        >
                            <div className="flex-1">
                                <div className="flex items-center gap-2 font-medium">
                                    {project.title}
                                    <Badge variant="outline">
                                        {project.status_label}
                                    </Badge>
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {project.customer}
                                    {project.site_address &&
                                        ` · ${project.site_address}`}
                                </div>
                            </div>
                            {project.planned_finish_on && (
                                <div className="text-sm text-muted-foreground">
                                    Ende: {formatDate(project.planned_finish_on)}
                                </div>
                            )}
                        </Link>
                    ))}
                    {projects.length === 0 && (
                        <p className="py-8 text-center text-muted-foreground">
                            Keine Projekte gefunden.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projekte', href: '/projects' }],
};
