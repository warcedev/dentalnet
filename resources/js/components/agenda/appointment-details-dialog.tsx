import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { dayLabel, splitDateTime } from '@/lib/agenda';
import appointments from '@/routes/appointments';
import patients from '@/routes/patients';
import type { AgendaAppointment } from '@/types';
import { Form, Link } from '@inertiajs/react';
import { AlertTriangle, CalendarClock } from 'lucide-react';

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    programada: 'outline',
    confirmada: 'default',
    en_curso: 'default',
    atendida: 'secondary',
    cancelada: 'destructive',
    no_asistio: 'destructive',
};

export function AppointmentDetailsDialog({
    appointment,
    onClose,
    onReschedule,
    canManage,
}: {
    appointment: AgendaAppointment | null;
    onClose: () => void;
    onReschedule: (a: AgendaAppointment) => void;
    canManage: boolean;
}) {
    if (!appointment) {
        return null;
    }

    const start = splitDateTime(appointment.starts_at);
    const end = splitDateTime(appointment.ends_at);
    const quickTransitions = appointment.transitions.filter((t) => t.value !== 'cancelada');
    const canCancel = appointment.transitions.some((t) => t.value === 'cancelada');
    const editable = !['atendida', 'cancelada', 'no_asistio'].includes(appointment.status);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle className="flex items-center gap-2">
                    {appointment.patient.name}
                    <Badge variant={STATUS_VARIANT[appointment.status]}>
                        {appointment.status_label}
                    </Badge>
                </DialogTitle>
                <DialogDescription>
                    {dayLabel(start.date, true)} · {start.time} a {end.time} ({appointment.duration}{' '}
                    min)
                </DialogDescription>

                {appointment.patient.has_allergies && (
                    <p className="flex items-center gap-2 rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white">
                        <AlertTriangle className="size-4" aria-hidden="true" />
                        Paciente con alergias registradas
                    </p>
                )}

                <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt className="text-muted-foreground">Tipo</dt>
                    <dd className="flex items-center gap-2">
                        <span
                            className="inline-block size-2.5 rounded-full"
                            style={{ background: appointment.type.color }}
                            aria-hidden="true"
                        />
                        {appointment.type.name}
                    </dd>
                    <dt className="text-muted-foreground">Profesional</dt>
                    <dd>{appointment.professional.name}</dd>
                    <dt className="text-muted-foreground">Consultorio</dt>
                    <dd>{appointment.room.name}</dd>
                    {appointment.patient.phone && (
                        <>
                            <dt className="text-muted-foreground">Teléfono</dt>
                            <dd>
                                <a
                                    href={`tel:${appointment.patient.phone.replace(/\s/g, '')}`}
                                    className="text-primary underline-offset-4 hover:underline"
                                >
                                    {appointment.patient.phone}
                                </a>
                            </dd>
                        </>
                    )}
                    {appointment.notes && (
                        <>
                            <dt className="text-muted-foreground">Notas</dt>
                            <dd className="whitespace-pre-line">{appointment.notes}</dd>
                        </>
                    )}
                    {appointment.cancellation_reason && (
                        <>
                            <dt className="text-muted-foreground">Motivo de cancelación</dt>
                            <dd>{appointment.cancellation_reason}</dd>
                        </>
                    )}
                </dl>

                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline" size="sm">
                        <Link href={patients.show(appointment.patient.id)}>Ver paciente</Link>
                    </Button>
                    {canManage && editable && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onReschedule(appointment)}
                        >
                            <CalendarClock aria-hidden="true" />
                            Reprogramar
                        </Button>
                    )}
                </div>

                {canManage && (quickTransitions.length > 0 || canCancel) && (
                    <div className="grid gap-2 border-t pt-3">
                        <p className="text-sm font-medium">Cambiar estado</p>
                        <div className="flex flex-wrap gap-2">
                            {quickTransitions.map((t) => (
                                <Form
                                    key={t.value}
                                    {...appointments.status.form(appointment.id)}
                                    options={{ preserveScroll: true, preserveState: true }}
                                    onSuccess={onClose}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <input type="hidden" name="status" value={t.value} />
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant={
                                                    t.value === 'no_asistio'
                                                        ? 'secondary'
                                                        : 'default'
                                                }
                                                disabled={processing}
                                            >
                                                {t.label}
                                            </Button>
                                            {errors.status && (
                                                <p className="mt-1 w-full text-xs text-destructive">
                                                    {errors.status}
                                                </p>
                                            )}
                                        </>
                                    )}
                                </Form>
                            ))}
                            {canCancel && (
                                <ConfirmDialog
                                    trigger={
                                        <Button size="sm" variant="destructive">
                                            Cancelar cita
                                        </Button>
                                    }
                                    title="¿Cancelar la cita?"
                                    description="El horario quedará libre y se cancelarán los recordatorios pendientes de esta cita."
                                    confirmLabel="Cancelar cita"
                                    reasonLabel="Motivo de la cancelación"
                                    hidden={{ status: 'cancelada' }}
                                    form={appointments.status.form(appointment.id)}
                                    destructive
                                />
                            )}
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
