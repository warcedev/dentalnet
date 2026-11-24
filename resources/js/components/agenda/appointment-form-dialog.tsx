import { FormField } from '@/components/form-field';
import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/native-select';
import { PatientPicker } from '@/components/patient-picker';
import { Textarea } from '@/components/textarea';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { splitDateTime } from '@/lib/agenda';
import appointments from '@/routes/appointments';
import type {
    AgendaAppointment,
    AgendaConfig,
    AppointmentTypeOption,
    NamedOption,
    PatientOption,
} from '@/types';
import { Form } from '@inertiajs/react';
import { useState } from 'react';

export type AppointmentDraft = {
    date: string;
    time: string;
    professional_id?: number | null;
    room_id?: number | null;
    appointment_type_id?: number | null;
    patient?: PatientOption | null;
    waiting_list_entry_id?: number | null;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Si se pasa, se reprograma esa cita; si no, se agenda una nueva. */
    appointment?: AgendaAppointment | null;
    draft: AppointmentDraft;
    professionals: NamedOption[];
    rooms: NamedOption[];
    types: AppointmentTypeOption[];
    patientOptions: PatientOption[] | undefined;
    config: AgendaConfig;
};

export function AppointmentFormDialog({
    open,
    onOpenChange,
    appointment,
    draft,
    professionals,
    rooms,
    types,
    patientOptions,
    config,
}: Props) {
    const editing = !!appointment;
    const initialType = appointment?.type.id ?? draft.appointment_type_id ?? types[0]?.id ?? null;
    const [patient, setPatient] = useState<PatientOption | null>(
        appointment
            ? {
                  id: appointment.patient.id,
                  name: appointment.patient.name,
                  document: '',
                  phone: appointment.patient.phone,
              }
            : (draft.patient ?? null),
    );
    const [typeId, setTypeId] = useState<number | null>(initialType);
    const [duration, setDuration] = useState<number>(
        appointment?.duration ?? types.find((t) => t.id === initialType)?.duration_minutes ?? 30,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>{editing ? 'Reprogramar cita' : 'Nueva cita'}</DialogTitle>
                <DialogDescription>
                    {editing
                        ? `${appointment.patient.name}. Se verificará que el nuevo horario esté libre.`
                        : 'Se verifica que el profesional, el consultorio y el paciente estén libres en ese horario.'}
                </DialogDescription>

                <Form
                    {...(editing
                        ? appointments.update.form(appointment.id)
                        : appointments.store.form())}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {!editing && (
                                <div className="grid gap-2">
                                    <label htmlFor="cita-paciente" className="text-sm font-medium">
                                        Paciente
                                        <span className="text-destructive" aria-hidden="true">
                                            {' '}
                                            *
                                        </span>
                                    </label>
                                    <PatientPicker
                                        id="cita-paciente"
                                        value={patient}
                                        onChange={setPatient}
                                        options={patientOptions}
                                        invalid={!!errors.patient_id}
                                        describedBy={
                                            errors.patient_id ? 'cita-paciente-error' : undefined
                                        }
                                    />
                                    <input
                                        type="hidden"
                                        name="patient_id"
                                        value={patient?.id ?? ''}
                                    />
                                    <InputError
                                        id="cita-paciente-error"
                                        message={errors.patient_id}
                                    />
                                </div>
                            )}
                            {draft.waiting_list_entry_id && (
                                <input
                                    type="hidden"
                                    name="waiting_list_entry_id"
                                    value={draft.waiting_list_entry_id}
                                />
                            )}

                            <FormField
                                id="cita-tipo"
                                label="Tipo de cita"
                                error={errors.appointment_type_id}
                                required
                            >
                                {(p) => (
                                    <NativeSelect
                                        {...p}
                                        name="appointment_type_id"
                                        value={typeId ?? ''}
                                        onChange={(e) => {
                                            const id = Number(e.target.value);
                                            setTypeId(id);
                                            setDuration(
                                                types.find((t) => t.id === id)?.duration_minutes ??
                                                    duration,
                                            );
                                        }}
                                    >
                                        {types.map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.name} ({t.duration_minutes} min)
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </FormField>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="cita-profesional"
                                    label="Profesional"
                                    error={errors.professional_id}
                                    required
                                >
                                    {(p) => (
                                        <NativeSelect
                                            {...p}
                                            name="professional_id"
                                            defaultValue={
                                                appointment?.professional.id ??
                                                draft.professional_id ??
                                                professionals[0]?.id
                                            }
                                        >
                                            {professionals.map((o) => (
                                                <option key={o.id} value={o.id}>
                                                    {o.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    )}
                                </FormField>
                                <FormField
                                    id="cita-consultorio"
                                    label="Consultorio"
                                    error={errors.room_id}
                                    required
                                >
                                    {(p) => (
                                        <NativeSelect
                                            {...p}
                                            name="room_id"
                                            defaultValue={
                                                appointment?.room.id ??
                                                draft.room_id ??
                                                rooms[0]?.id
                                            }
                                        >
                                            {rooms.map((o) => (
                                                <option key={o.id} value={o.id}>
                                                    {o.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    )}
                                </FormField>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <FormField
                                    id="cita-fecha"
                                    label="Fecha"
                                    error={errors.date}
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            type="date"
                                            name="date"
                                            defaultValue={
                                                appointment
                                                    ? splitDateTime(appointment.starts_at).date
                                                    : draft.date
                                            }
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="cita-hora"
                                    label="Hora"
                                    error={errors.start_time ?? errors.starts_at}
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            type="time"
                                            name="start_time"
                                            min={config.opens_at}
                                            max={config.closes_at}
                                            step={config.slot_minutes * 60}
                                            defaultValue={
                                                appointment
                                                    ? splitDateTime(appointment.starts_at).time
                                                    : draft.time
                                            }
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="cita-duracion"
                                    label="Duración (min)"
                                    error={errors.duration_minutes}
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            type="number"
                                            name="duration_minutes"
                                            min={5}
                                            max={480}
                                            step={5}
                                            value={duration}
                                            onChange={(e) => setDuration(Number(e.target.value))}
                                        />
                                    )}
                                </FormField>
                            </div>

                            <FormField id="cita-notas" label="Notas" error={errors.notes}>
                                {(p) => (
                                    <Textarea
                                        {...p}
                                        name="notes"
                                        rows={2}
                                        defaultValue={appointment?.notes ?? ''}
                                    />
                                )}
                            </FormField>

                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing || (!editing && !patient)}
                                >
                                    {processing && <Spinner />}
                                    {editing ? 'Guardar cambios' : 'Agendar cita'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
