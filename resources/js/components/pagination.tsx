import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Link } from '@inertiajs/react';

function cleanLabel(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»').trim();
}

export function Pagination<T>({
    page,
    itemLabel = 'registros',
}: {
    page: Paginated<T>;
    itemLabel?: string;
}) {
    if (page.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p className="text-sm text-muted-foreground" aria-live="polite">
                Mostrando {page.from}–{page.to} de {page.total} {itemLabel}
            </p>
            {page.last_page > 1 && (
                <nav aria-label="Paginación">
                    <ul className="flex flex-wrap gap-1">
                        {page.links.map((link, index) => {
                            const label = cleanLabel(link.label);
                            const base =
                                'inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm';

                            return (
                                <li key={`${index}-${label}`}>
                                    {link.url ? (
                                        <Link
                                            href={link.url}
                                            preserveScroll
                                            preserveState
                                            aria-current={link.active ? 'page' : undefined}
                                            className={cn(
                                                base,
                                                link.active
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'hover:bg-accent',
                                            )}
                                        >
                                            {label}
                                        </Link>
                                    ) : (
                                        <span
                                            aria-disabled="true"
                                            className={cn(base, 'opacity-50')}
                                        >
                                            {label}
                                        </span>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </nav>
            )}
        </div>
    );
}
