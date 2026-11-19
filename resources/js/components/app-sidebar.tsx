import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    Calculator,
    CalendarClock,
    CalendarRange,
    ChevronDown,
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
import { useEffect, useRef, useState } from 'react';
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
        title: 'Einteilung',
        href: '/assignments',
        icon: CalendarRange,
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
    { title: 'Baukalkulation', href: '/calculations', icon: Calculator },
    { title: 'Projekte', href: '/projects', icon: FolderKanban },
    { title: 'Regieberichte', href: '/site-reports', icon: FileSignature },
];

// Preise und Angebotssummen — für die Rolle Baustelle ausgeblendet.
const financialProjectHrefs = ['/offers', '/calculations'];

const invoiceNavItems: NavItem[] = [
    {
        title: 'Ausgangsrechnungen',
        href: '/outgoing-invoices',
        icon: FileOutput,
    },
    {
        title: 'Eingangsrechnungen',
        href: '/incoming-invoices',
        icon: FileInput,
    },
    { title: 'Excel-Import', href: '/imports', icon: FileSpreadsheet },
];

export function AppSidebar() {
    const { tenancy } = usePage().props;
    const contentRef = useRef<HTMLDivElement>(null);
    const [moreBelow, setMoreBelow] = useState(false);

    // Auf niedrigen Bildschirmen passt das Menü nicht ganz — ein Pfeil
    // am unteren Rand zeigt, dass darunter noch Einträge liegen.
    useEffect(() => {
        const element = contentRef.current;

        if (!element) {
            return;
        }

        const update = () =>
            setMoreBelow(
                element.scrollHeight -
                    element.scrollTop -
                    element.clientHeight >
                    8,
            );
        const observer = new ResizeObserver(update);

        observer.observe(element);
        Array.from(element.children).forEach((child) =>
            observer.observe(child),
        );
        element.addEventListener('scroll', update, { passive: true });

        return () => {
            observer.disconnect();
            element.removeEventListener('scroll', update);
        };
    }, []);

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

            <SidebarContent ref={contentRef}>
                <NavMain
                    items={
                        // Die Baustelle startet mit „Mein Tag".
                        tenancy.role === 'site'
                            ? mainNavItems.map((item, index) =>
                                  index === 0
                                      ? { ...item, title: 'Mein Tag' }
                                      : item,
                              )
                            : mainNavItems
                    }
                    label="Übersicht"
                />
                <NavMain
                    items={
                        tenancy.canViewFinancials
                            ? projectNavItems
                            : projectNavItems.filter(
                                  (item) =>
                                      !financialProjectHrefs.includes(
                                          item.href as string,
                                      ),
                              )
                    }
                    label="Vertrieb & Projekte"
                />
                {tenancy.canViewFinancials && (
                    <NavMain items={invoiceNavItems} label="Belege" />
                )}
                <NavMain
                    items={masterDataNavItems}
                    label="Stammdaten"
                    defaultOpen={false}
                />
            </SidebarContent>
            {moreBelow && (
                <div className="pointer-events-none relative -mt-8 flex h-8 shrink-0 items-end justify-center bg-gradient-to-t from-sidebar to-transparent pb-0.5 group-data-[collapsible=icon]:hidden">
                    <ChevronDown className="size-4 text-sidebar-foreground/60" />
                </div>
            )}

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
