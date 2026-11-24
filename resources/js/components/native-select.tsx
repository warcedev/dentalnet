import { cn } from '@/lib/utils';
import type { SelectHTMLAttributes } from 'react';

/** <select> nativo con el estilo de los inputs (mejor soporte de teclado y lectores de pantalla). */
export function NativeSelect({ className, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select
            {...props}
            className={cn(
                'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 aria-invalid:border-destructive',
                className,
            )}
        />
    );
}
