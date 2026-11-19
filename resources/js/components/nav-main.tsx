import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

/**
 * Menügruppe. Mit Beschriftung ist sie einklappbar: Der Zustand wird je
 * Gerät gemerkt, und die Gruppe der aktuellen Seite ist immer offen —
 * so bleibt das Menü kurz genug, dass nichts unter den Bildrand rutscht.
 */
export function NavMain({
    items = [],
    label,
    defaultOpen = true,
}: {
    items: NavItem[];
    label?: string;
    defaultOpen?: boolean;
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { state } = useSidebar();
    const storageKey = `nav-group:${label ?? 'main'}`;
    const [open, setOpen] = useState(() => {
        if (typeof window === 'undefined') {
            return defaultOpen;
        }

        try {
            const stored = window.localStorage.getItem(storageKey);

            return stored === null ? defaultOpen : stored === '1';
        } catch {
            // Ohne Speicher gilt der Standard.
            return defaultOpen;
        }
    });

    const toggle = () => {
        const next = !open;
        setOpen(next);

        try {
            window.localStorage.setItem(storageKey, next ? '1' : '0');
        } catch {
            // Nicht merkbar — für diese Sitzung gilt es trotzdem.
        }
    };

    const hasActive = items.some((item) => isCurrentOrParentUrl(item.href));
    // Zur Symbolleiste eingeklappt gibt es keine Überschriften mehr —
    // dort müssen alle Einträge erreichbar bleiben.
    const expanded =
        label === undefined || open || hasActive || state === 'collapsed';

    return (
        <SidebarGroup className="px-2 py-0">
            {label && (
                <SidebarGroupLabel asChild>
                    <button
                        type="button"
                        onClick={toggle}
                        aria-expanded={expanded}
                        className="w-full justify-between hover:text-sidebar-foreground"
                    >
                        {label}
                        <ChevronRight
                            className={`transition-transform ${expanded ? 'rotate-90' : ''}`}
                        />
                    </button>
                </SidebarGroupLabel>
            )}
            {expanded && (
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentOrParentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            )}
        </SidebarGroup>
    );
}
