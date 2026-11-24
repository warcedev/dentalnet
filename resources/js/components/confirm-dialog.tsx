import { FormField } from '@/components/form-field';
import { Textarea } from '@/components/textarea';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { Form } from '@inertiajs/react';
import { useState, type ComponentProps, type ReactNode } from 'react';

type FormTarget = Pick<ComponentProps<typeof Form>, 'action' | 'method'>;

/**
 * Confirmación de una acción sensible (archivar, desactivar). Si `reasonLabel`
 * está definido, pide un motivo obligatorio que se envía como `reason`.
 */
export function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel,
    form,
    reasonLabel,
    destructive = false,
    hidden = {},
}: {
    trigger: ReactNode;
    title: string;
    description: string;
    confirmLabel: string;
    form: FormTarget;
    reasonLabel?: string;
    destructive?: boolean;
    /** Campos ocultos adicionales que se envían con el formulario. */
    hidden?: Record<string, string>;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {Object.entries(hidden).map(([name, value]) => (
                                <input key={name} type="hidden" name={name} value={value} />
                            ))}
                            {reasonLabel && (
                                <FormField
                                    id="reason"
                                    label={reasonLabel}
                                    error={errors.reason}
                                    required
                                >
                                    {(p) => (
                                        <Textarea
                                            {...p}
                                            name="reason"
                                            rows={3}
                                            required
                                            minLength={5}
                                        />
                                    )}
                                </FormField>
                            )}
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant={destructive ? 'destructive' : 'default'}
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    {confirmLabel}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
