import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/**
 * Suchfeld für Listen: schickt die Eingabe erst ab, wenn kurz nicht
 * mehr getippt wird — sonst lädt die Liste bei jedem Buchstaben neu.
 */
export function SearchInput({
    value,
    onSearch,
    placeholder = 'Suchen …',
}: {
    value: string;
    onSearch: (q: string) => void;
    placeholder?: string;
}) {
    const [q, setQ] = useState(value);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timeout = setTimeout(() => onSearch(q), 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    return (
        <div className="relative w-full max-w-xs">
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                type="search"
                value={q}
                onChange={(event) => setQ(event.target.value)}
                placeholder={placeholder}
                className="pl-8"
                aria-label="Suchen"
            />
        </div>
    );
}

export function SortSelect({
    value,
    options,
    onChange,
}: {
    value: string;
    options: { value: string; label: string }[];
    onChange: (sort: string) => void;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger className="w-auto" aria-label="Sortierung">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/** Blättern — erscheint nur, wenn es mehr als eine Seite gibt. */
export function Pager({
    page,
    lastPage,
    onPage,
}: {
    page: number;
    lastPage: number;
    onPage: (page: number) => void;
}) {
    if (lastPage <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-center gap-3 text-sm">
            <Button
                variant="outline"
                size="icon"
                aria-label="Vorige Seite"
                disabled={page <= 1}
                onClick={() => onPage(page - 1)}
            >
                <ChevronLeft className="size-4" />
            </Button>
            <span className="text-muted-foreground">
                Seite {page} von {lastPage}
            </span>
            <Button
                variant="outline"
                size="icon"
                aria-label="Nächste Seite"
                disabled={page >= lastPage}
                onClick={() => onPage(page + 1)}
            >
                <ChevronRight className="size-4" />
            </Button>
        </div>
    );
}
