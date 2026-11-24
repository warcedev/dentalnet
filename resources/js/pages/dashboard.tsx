import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { splitDateTime } from '@/lib/agenda';
import { formatDate } from '@/lib/format';
import { visibleNavGroups } from '@/lib/navigation';
import { agenda, dashboard } from '@/routes';
import patients from '@/routes/patients';
import { edit as securityEdit } from '@/routes/security';
import type { AgendaAppointment } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays, ShieldAlert } from 'lucide-react';

const ROLE_LABELS: Record<string, string> = {
    administrador: 'Administrador',
    odontologo: 'Odontólogo',
    recepcion: 'Recepción',
};

export default function Dashboard({
    today_appointments,
    only_mine,
}: {
    today_appointments: AgendaAppointment[] | null;
    only_mine: boolean;
}) {
    const { auth } = usePage().props;
    const can = useCan();
    const shortcuts = visibleNavGroups(can)
        .flatMap((group) => group.items)
        .filter((item) => item.title !== 'Inicio');

    return (
        <>
            <Head title="Inicio" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`Hola, ${auth.user.name}`}
                    description={`${auth.roles.map((r) => ROLE_LABELS[r] ?? r).join(' · ')} — ${formatDate(new Date())}`}
                />

                {!auth.user.two_factor_enabled && (
                    <Alert>
                        <ShieldAlert aria-hidden="true" />
                        <AlertTitle>Proteja su cuenta</AlertTitle>
                        <AlertDescription>
                            <p>
                                Active la verificación en dos pasos desde{' '}
                                <Link
                                    href={securityEdit()}
                                    className="font-medium underline underline-offset-4"
                                >
                                    Ajustes › Seguridad
                                </Link>
                                . El sistema guarda datos de salud de sus pacientes.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                {shortcuts.length > 0 && (
                    <section aria-labelledby="accesos" className="space-y-3">
                        <h2 id="accesos" className="text-lg font-medium">
                            Accesos rápidos
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {shortcuts.map((item) => (
                                <Link
                                    key={item.title}
                                    href={item.href}
                                    className="rounded-xl outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                >
                                    <Card className="h-full transition-colors hover:bg-accent">
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                {item.icon && (
                                                    <item.icon
                                                        className="size-5 text-primary"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {item.title}
                                            </CardTitle>
                                            <CardDescription>
                                                Ir a {item.title.toLowerCase()}
                                            </CardDescription>
                                        </CardHeader>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {today_appointments !== null && (
                    <section aria-labelledby="citas-hoy" className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h2 id="citas-hoy" className="text-lg font-medium">
                                {only_mine ? 'Mis citas de hoy' : 'Citas de hoy'}
                            </h2>
                            <Button asChild variant="outline" size="sm">
                                <Link href={agenda()}>
                                    <CalendarDays aria-hidden="true" />
                                    Ver agenda
                                </Link>
                            </Button>
                        </div>
                        {today_appointments.length === 0 ? (
                            <p className="rounded-xl border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                                No hay citas para hoy.
                            </p>
                        ) : (
                            <ul className="divide-y rounded-xl border">
                                {today_appointments.map((a) => (
                                    <li
                                        key={a.id}
                                        className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-sm"
                                    >
                                        <span className="w-24 font-medium tabular-nums">
                                            {splitDateTime(a.starts_at).time}–
                                            {splitDateTime(a.ends_at).time}
                                        </span>
                                        <Link
                                            href={patients.show(a.patient.id)}
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            {a.patient.name}
                                        </Link>
                                        <span className="text-muted-foreground">
                                            {a.type.name}
                                            {!only_mine && ` · ${a.professional.name}`} ·{' '}
                                            {a.room.name}
                                        </span>
                                        <Badge
                                            variant={
                                                a.status === 'no_asistio'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                            className="ml-auto"
                                        >
                                            {a.status_label}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
};
