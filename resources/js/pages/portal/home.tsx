import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatGuaranies, formatLocalDateTime } from '@/lib/format';
import { Head } from '@inertiajs/react';
import { CalendarDays, Phone, Sparkles, Wallet } from 'lucide-react';

type Props = {
    appointments: {
        id: number;
        starts_at: string;
        type: string;
        professional: string;
        status_label: string;
    }[];
    balance: { proposed: number; billed: number; paid: number; balance: number };
    last_cleaning: string | null;
    next_cleaning: string | null;
    clinic: { phone: string | null; address: string | null };
};

export default function PortalHome({
    appointments,
    balance,
    last_cleaning,
    next_cleaning,
    clinic,
}: Props) {
    return (
        <>
            <Head title="Portal del paciente" />
            <h1 className="text-2xl font-semibold tracking-tight">Inicio</h1>
            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CalendarDays className="size-4" aria-hidden="true" />
                            <h2>Próximos turnos</h2>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {appointments.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No tiene turnos agendados.
                            </p>
                        ) : (
                            <ul className="grid gap-3">
                                {appointments.map((a) => (
                                    <li key={a.id} className="rounded-lg border p-3">
                                        <p className="font-medium">
                                            {formatLocalDateTime(a.starts_at)}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {a.type} · {a.professional} · {a.status_label}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Wallet className="size-4" aria-hidden="true" />
                            <h2>Estado de cuenta</h2>
                        </CardTitle>
                        <CardDescription>
                            Tratamientos realizados o en curso menos pagos registrados.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt className="text-muted-foreground">Total de tratamientos</dt>
                                <dd className="font-medium tabular-nums">
                                    {formatGuaranies(balance.billed)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Pagado</dt>
                                <dd className="font-medium tabular-nums">
                                    {formatGuaranies(balance.paid)}
                                </dd>
                            </div>
                            <div className="col-span-2 rounded-lg bg-muted p-3">
                                <dt className="text-muted-foreground">
                                    {balance.balance >= 0 ? 'Saldo pendiente' : 'Saldo a su favor'}
                                </dt>
                                <dd className="text-xl font-semibold tabular-nums">
                                    {formatGuaranies(Math.abs(balance.balance))}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Sparkles className="size-4" aria-hidden="true" />
                            <h2>Limpieza dental</h2>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm">
                        <p>Última limpieza: {formatDate(last_cleaning, 'sin registro')}</p>
                        <p>
                            Próxima recomendada:{' '}
                            {next_cleaning
                                ? formatDate(next_cleaning)
                                : 'consulte en el consultorio'}
                        </p>
                    </CardContent>
                </Card>
                {(clinic.phone || clinic.address) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Phone className="size-4" aria-hidden="true" />
                                <h2>Contacto del consultorio</h2>
                            </CardTitle>
                            <CardDescription>
                                Para pedir o cambiar un turno, comuníquese con recepción.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {clinic.phone && <p>Teléfono: {clinic.phone}</p>}
                            {clinic.address && <p>Dirección: {clinic.address}</p>}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
