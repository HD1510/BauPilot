import { Link, router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus, Settings2 } from 'lucide-react';
import { CompanyBadge } from '@/components/company-badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { companyRoleLabels } from '@/types';

/**
 * Firmen-Plakette und Wechsler in der Sidebar. Der Wechsel ist nur auf
 * Firmen möglich, die der Server für den Benutzer kennt — die Liste kommt
 * aus den geteilten Props, die Prüfung passiert serverseitig erneut.
 */
export function CompanySwitcher() {
    const { tenancy } = usePage().props;
    const active = tenancy.activeCompany;

    if (active === null) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton asChild>
                        <Link href="/companies/create">
                            <Building2 className="size-4" />
                            <span>Firma anlegen</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            aria-label={`Aktive Firma: ${active.name}`}
                        >
                            <CompanyBadge
                                shortCode={active.short_code}
                                color={active.color}
                            />
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">
                                    {active.name}
                                </span>
                                {tenancy.role && (
                                    <span className="truncate text-xs text-muted-foreground">
                                        {companyRoleLabels[tenancy.role]}
                                    </span>
                                )}
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                        align="start"
                    >
                        <DropdownMenuLabel>Firma wechseln</DropdownMenuLabel>
                        {tenancy.companies.map((company) => (
                            <DropdownMenuItem
                                key={company.id}
                                onSelect={() =>
                                    company.id !== active.id &&
                                    router.post(
                                        `/companies/${company.id}/switch`,
                                    )
                                }
                            >
                                <CompanyBadge
                                    shortCode={company.short_code}
                                    color={company.color}
                                    className="size-6 text-[10px]"
                                />
                                <span className="flex-1 truncate">
                                    {company.name}
                                </span>
                                {company.id === active.id && (
                                    <Check className="size-4" />
                                )}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href="/companies">
                                <Settings2 className="size-4" />
                                Firmen verwalten
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href="/companies/create">
                                <Plus className="size-4" />
                                Neue Firma
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
