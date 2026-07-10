import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarClock,
    Clock,
    FileSpreadsheet,
    Car,
    Contact,
    FileInput,
    FileOutput,
    FileSignature,
    FileText,
    FolderKanban,
    HardHat,
    LayoutGrid,
    Package,
    SquareCheckBig,
    Tags,
    Truck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { CompanySwitcher } from '@/components/company-switcher';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Fristen',
        href: '/deadlines',
        icon: CalendarClock,
    },
    {
        title: 'Aufgaben',
        href: '/tasks',
        icon: SquareCheckBig,
    },
    {
        title: 'Zeiten',
        href: '/time-entries',
        icon: Clock,
    },
    {
        title: 'Firmen',
        href: '/companies',
        icon: Building2,
    },
];

const masterDataNavItems: NavItem[] = [
    { title: 'Kunden', href: '/customers', icon: Contact },
    { title: 'Lieferanten', href: '/suppliers', icon: Truck },
    { title: 'Kostenarten', href: '/cost-types', icon: Tags },
    { title: 'Mitarbeiter', href: '/employees', icon: HardHat },
    { title: 'Fahrzeuge', href: '/vehicles', icon: Car },
    { title: 'Material', href: '/materials', icon: Package },
];

const projectNavItems: NavItem[] = [
    { title: 'Angebote', href: '/offers', icon: FileText },
    { title: 'Projekte', href: '/projects', icon: FolderKanban },
    { title: 'Regieberichte', href: '/site-reports', icon: FileSignature },
];

const invoiceNavItems: NavItem[] = [
    { title: 'Ausgangsrechnungen', href: '/outgoing-invoices', icon: FileOutput },
    { title: 'Eingangsrechnungen', href: '/incoming-invoices', icon: FileInput },
    { title: 'Excel-Import', href: '/imports', icon: FileSpreadsheet },
];

export function AppSidebar() {
    const { tenancy } = usePage().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <CompanySwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label="Übersicht" />
                <NavMain
                    items={
                        tenancy.canViewFinancials
                            ? projectNavItems
                            : projectNavItems.filter(
                                  (item) => item.href !== '/offers',
                              )
                    }
                    label="Vertrieb & Projekte"
                />
                {tenancy.canViewFinancials && (
                    <NavMain items={invoiceNavItems} label="Belege" />
                )}
                <NavMain items={masterDataNavItems} label="Stammdaten" />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
