import type { FieldControlProps } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { useState } from 'react';

const format = (digits: string) =>
    digits === '' ? '' : Number(digits).toLocaleString('es-PY').replace(/,/g, '.');

/**
 * Monto en guaraníes: muestra separadores de miles mientras se escribe
 * ("1.250.000") y envía el entero sin puntos en un campo oculto.
 */
export function MoneyInput({
    name,
    defaultValue,
    value,
    onValueChange,
    ...field
}: FieldControlProps & {
    name: string;
    defaultValue?: number | null;
    value?: number | null;
    onValueChange?: (value: number | null) => void;
}) {
    const [internal, setInternal] = useState(defaultValue != null ? String(defaultValue) : '');
    const digits = value !== undefined ? (value === null ? '' : String(value)) : internal;

    return (
        <div className="relative">
            <span
                className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                aria-hidden="true"
            >
                Gs.
            </span>
            <Input
                {...field}
                inputMode="numeric"
                autoComplete="off"
                className="pl-10 tabular-nums"
                value={format(digits)}
                onChange={(e) => {
                    const clean = e.target.value
                        .replace(/\D/g, '')
                        .replace(/^0+(?=\d)/, '')
                        .slice(0, 9);
                    setInternal(clean);
                    onValueChange?.(clean === '' ? null : Number(clean));
                }}
            />
            <input type="hidden" name={name} value={digits} />
        </div>
    );
}
