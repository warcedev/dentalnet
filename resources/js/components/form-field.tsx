import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import type { ReactNode } from 'react';

export type FieldControlProps = {
    id: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
    'aria-required'?: boolean;
};

/**
 * Campo de formulario accesible: vincula etiqueta, ayuda y error con el
 * control (aria-describedby / aria-invalid) y muestra el error junto al campo.
 */
export function FormField({
    id,
    label,
    error,
    hint,
    required = false,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    children: (props: FieldControlProps) => ReactNode;
}) {
    const hintId = hint ? `${id}-ayuda` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden="true">
                        {' '}
                        *
                    </span>
                )}
            </Label>
            {children({
                id,
                'aria-invalid': error ? true : undefined,
                'aria-describedby': describedBy,
                'aria-required': required || undefined,
            })}
            {hint && (
                <p id={hintId} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            <InputError id={errorId} message={error} />
        </div>
    );
}
