import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import portal from '@/routes/portal';
import { Form, Link, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import type { ReactNode } from 'react';

const LINKS = [
    { title: 'Inicio', href: portal.home() },
    { title: 'Mi odontograma', href: portal.odontogram() },
    { title: 'Historial', href: portal.history() },
    { title: 'Recetas y recibos', href: portal.documents() },
];

/** Marco del portal del paciente: sin el menú del personal. */
export default function PortalLayout({ children }: { children: ReactNode }) {
    const account = usePage().props.portal as { name: string | null; email: string } | null;
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <div className="min-h-svh bg-background">
            <a
                href="#contenido"
                className="sr-only focus:not-sr-only focus:absolute focus:m-2 focus:rounded focus:bg-background focus:p-2"
            >
                Saltar al contenido
            </a>
            <header className="border-b bg-card">
                <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <Link href={portal.home()} className="flex items-center gap-2">
                        <AppLogo />
                    </Link>
                    <div className="flex items-center gap-3 text-sm">
                        {account?.name && (
                            <span className="text-muted-foreground">{account.name}</span>
                        )}
                        <Form {...portal.logout.form()}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                >
                                    <LogOut aria-hidden="true" />
                                    Salir
                                </Button>
                            )}
                        </Form>
                    </div>
                </div>
                <nav aria-label="Secciones del portal" className="mx-auto max-w-5xl px-4">
                    <ul className="-mb-px flex gap-1 overflow-x-auto">
                        {LINKS.map((l) => {
                            const active = isCurrentUrl(l.href);

                            return (
                                <li key={l.title}>
                                    <Link
                                        href={l.href}
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'inline-flex border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                            active
                                                ? 'border-primary text-foreground'
                                                : 'border-transparent text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {l.title}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>
            </header>
            <main id="contenido" className="mx-auto grid max-w-5xl gap-4 px-4 py-6">
                {children}
            </main>
        </div>
    );
}
