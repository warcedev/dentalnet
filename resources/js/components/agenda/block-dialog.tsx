import { FormField } from '@/components/form-field';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import blocks from '@/routes/blocks';
import type { NamedOption } from '@/types';
import { Form } from '@inertiajs/react';

export function BlockDialog({
    open,
    onOpenChange,
    date,
    professionals,
    rooms,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    date: string;
    professionals: NamedOption[];
    rooms: NamedOption[];
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>Bloquear horario</DialogTitle>
                <DialogDescription>
                    Para vacaciones, capacitaciones o mantenimiento. Sin profesional ni consultorio,
                    se bloquea toda la clínica.
                </DialogDescription>
                <Form
                    {...blocks.store.form()}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="bloqueo-profesional"
                                    label="Profesional"
                                    error={errors.professional_id}
                                >
                                    {(p) => (
                                        <NativeSelect {...p} name="professional_id" defaultValue="">
                                            <option value="">Todos</option>
                                            {professionals.map((o) => (
                                                <option key={o.id} value={o.id}>
                                                    {o.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    )}
                                </FormField>
                                <FormField
                                    id="bloqueo-consultorio"
                                    label="Consultorio"
                                    error={errors.room_id}
                                >
                                    {(p) => (
                                        <NativeSelect {...p} name="room_id" defaultValue="">
                                            <option value="">Todos</option>
                                            {rooms.map((o) => (
                                                <option key={o.id} value={o.id}>
                                                    {o.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    )}
                                </FormField>
                                <FormField
                                    id="bloqueo-inicio"
                                    label="Desde"
                                    error={errors.starts_at}
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            type="datetime-local"
                                            name="starts_at"
                                            defaultValue={`${date}T12:00`}
                                        />
                                    )}
                                </FormField>
                                <FormField
                                    id="bloqueo-fin"
                                    label="Hasta"
                                    error={errors.ends_at}
                                    required
                                >
                                    {(p) => (
                                        <Input
                                            {...p}
                                            type="datetime-local"
                                            name="ends_at"
                                            defaultValue={`${date}T14:00`}
                                        />
                                    )}
                                </FormField>
                            </div>
                            <FormField
                                id="bloqueo-motivo"
                                label="Motivo"
                                error={errors.reason}
                                required
                            >
                                {(p) => (
                                    <Input
                                        {...p}
                                        name="reason"
                                        placeholder="Ej.: capacitación, vacaciones"
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
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Bloquear
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
