import { Link } from '@inertiajs/react';
import {
    Building2,
    Car,
    Contact,
    HardHat,
    LayoutGrid,
    Package,
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

export function AppSidebar() {
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
                <NavMain items={masterDataNavItems} label="Stammdaten" />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
