import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { PatientOption } from '@/types';
import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * Buscador de pacientes accesible (patrón combobox). Pide resultados al
 * servidor con una recarga parcial de Inertia (`only: ['patient_options']`).
 */
export function PatientPicker({
    id,
    value,
    onChange,
    options,
    invalid,
    describedBy,
}: {
    id: string;
    value: PatientOption | null;
    onChange: (patient: PatientOption | null) => void;
    options: PatientOption[] | undefined;
    invalid?: boolean;
    describedBy?: string;
}) {
    const [term, setTerm] = useState('');
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const [loading, setLoading] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const results = options ?? [];
    const listId = `${id}-opciones`;

    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const search = (text: string) => {
        setTerm(text);
        setOpen(true);
        setActive(0);

        if (timer.current) {
            clearTimeout(timer.current);
        }

        if (text.trim().length < 2) {
            return;
        }

        timer.current = setTimeout(() => {
            router.reload({
                only: ['patient_options'],
                data: { buscar_paciente: text },
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            });
        }, 250);
    };

    const choose = (patient: PatientOption) => {
        onChange(patient);
        setOpen(false);
        setTerm('');
    };

    if (value) {
        return (
            <div className="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm">
                <span>
                    <span className="font-medium">{value.name}</span>
                    <span className="text-muted-foreground"> · {value.document}</span>
                </span>
                <button
                    type="button"
                    onClick={() => onChange(null)}
                    className="rounded p-1 hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    aria-label={`Quitar a ${value.name}`}
                >
                    <X className="size-4" aria-hidden="true" />
                </button>
            </div>
        );
    }

    const showList = open && term.trim().length >= 2;

    return (
        <div className="relative">
            <Input
                id={id}
                role="combobox"
                aria-expanded={showList}
                aria-controls={listId}
                aria-autocomplete="list"
                aria-activedescendant={
                    showList && results[active] ? `${listId}-${results[active].id}` : undefined
                }
                aria-invalid={invalid || undefined}
                aria-describedby={describedBy}
                placeholder="Nombre, documento o teléfono"
                autoComplete="off"
                value={term}
                onChange={(e) => search(e.target.value)}
                onFocus={() => setOpen(true)}
                onBlur={() => setTimeout(() => setOpen(false), 150)}
                onKeyDown={(e) => {
                    if (!showList) {
                        return;
                    }

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setActive((i) => Math.min(i + 1, results.length - 1));
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        setActive((i) => Math.max(i - 1, 0));
                    } else if (e.key === 'Enter' && results[active]) {
                        e.preventDefault();
                        choose(results[active]);
                    } else if (e.key === 'Escape') {
                        setOpen(false);
                    }
                }}
            />
            {showList && (
                <ul
                    id={listId}
                    role="listbox"
                    aria-label="Pacientes encontrados"
                    className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-md border bg-popover p-1 shadow-md"
                >
                    {loading && results.length === 0 && (
                        <li className="px-2 py-1.5 text-sm text-muted-foreground">Buscando…</li>
                    )}
                    {!loading && results.length === 0 && (
                        <li className="px-2 py-1.5 text-sm text-muted-foreground">
                            Sin resultados. Registre al paciente primero.
                        </li>
                    )}
                    {results.map((p, i) => (
                        <li
                            key={p.id}
                            id={`${listId}-${p.id}`}
                            role="option"
                            aria-selected={i === active}
                            onMouseDown={(e) => {
                                e.preventDefault();
                                choose(p);
                            }}
                            className={cn(
                                'cursor-pointer rounded px-2 py-1.5 text-sm',
                                i === active && 'bg-accent',
                            )}
                        >
                            <span className="font-medium">{p.name}</span>
                            <span className="text-muted-foreground">
                                {' '}
                                · {p.document}
                                {p.phone && ` · ${p.phone}`}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
