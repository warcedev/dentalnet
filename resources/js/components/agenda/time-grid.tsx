import {
    fromMinutes,
    layoutLanes,
    nowMinutesLocal,
    splitDateTime,
    toMinutes,
    todayLocal,
} from '@/lib/agenda';
import { cn } from '@/lib/utils';
import type { AgendaAppointment, AgendaConfig, ScheduleBlockItem } from '@/types';
import { AlertTriangle, Ban, Check } from 'lucide-react';

const PX_PER_MINUTE = 1.4;

export type GridColumn = {
    key: string;
    label: string;
    sublabel?: string;
    date: string;
    /** Valores con los que se precarga una cita nueva desde esta columna. */
    defaults: { professional_id?: number; room_id?: number };
    appointments: AgendaAppointment[];
    blocks: ScheduleBlockItem[];
};

function blockRange(block: ScheduleBlockItem, date: string, opens: number, closes: number) {
    const start = splitDateTime(block.starts_at);
    const end = splitDateTime(block.ends_at);
    const from = start.date < date ? opens : Math.max(start.minutes, opens);
    const to = end.date > date ? closes : Math.min(end.minutes, closes);

    return to > from ? { from, to } : null;
}

/**
 * Grilla horaria con una columna por profesional, consultorio o día. Las
 * citas que se superponen se dibujan lado a lado.
 */
export function TimeGrid({
    columns,
    config,
    canManage,
    onSlot,
    onAppointment,
}: {
    columns: GridColumn[];
    config: AgendaConfig;
    canManage: boolean;
    onSlot: (column: GridColumn, time: string) => void;
    onAppointment: (appointment: AgendaAppointment) => void;
}) {
    const opens = toMinutes(config.opens_at);
    const closes = toMinutes(config.closes_at);
    const slot = config.slot_minutes;
    const height = (closes - opens) * PX_PER_MINUTE;
    const slots = Array.from(
        { length: Math.ceil((closes - opens) / slot) },
        (_, i) => opens + i * slot,
    );
    const hours = slots.filter((m) => m % 60 === 0);
    const today = todayLocal();
    const now = nowMinutesLocal();

    if (columns.length === 0) {
        return (
            <p className="rounded-md border px-3 py-6 text-center text-sm text-muted-foreground">
                No hay columnas para mostrar.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-xl border">
            <div
                className="grid min-w-fit"
                style={{
                    gridTemplateColumns: `3.5rem repeat(${columns.length}, minmax(9rem, 1fr))`,
                }}
            >
                <div className="sticky left-0 z-20 border-b bg-background" />
                {columns.map((col) => (
                    <div
                        key={col.key}
                        className="border-b border-l bg-muted/40 px-2 py-2 text-center"
                    >
                        <p className="truncate text-sm font-medium">{col.label}</p>
                        {col.sublabel && (
                            <p className="truncate text-xs text-muted-foreground">{col.sublabel}</p>
                        )}
                    </div>
                ))}

                <div
                    className="relative sticky left-0 z-10 bg-background"
                    style={{ height }}
                    aria-hidden="true"
                >
                    {hours.map((m) => (
                        <span
                            key={m}
                            className={cn(
                                'absolute right-2 text-xs text-muted-foreground tabular-nums',
                                // La primera hora va pegada al borde para que no quede cortada.
                                m === opens ? 'translate-y-0.5' : '-translate-y-1/2',
                            )}
                            style={{ top: (m - opens) * PX_PER_MINUTE }}
                        >
                            {fromMinutes(m)}
                        </span>
                    ))}
                </div>

                {columns.map((col) => {
                    const visible = col.appointments.filter((a) => a.status !== 'cancelada');
                    const lanes = layoutLanes(
                        visible.map((a) => ({
                            id: a.id,
                            start: splitDateTime(a.starts_at).minutes,
                            end: splitDateTime(a.ends_at).minutes,
                        })),
                    );

                    return (
                        <div key={col.key} className="relative border-l" style={{ height }}>
                            {/* Líneas de horas */}
                            {slots.map((m) => (
                                <div
                                    key={m}
                                    className={cn(
                                        'absolute inset-x-0 border-t',
                                        m % 60 === 0
                                            ? 'border-border'
                                            : 'border-dashed border-border/40',
                                    )}
                                    style={{ top: (m - opens) * PX_PER_MINUTE }}
                                    aria-hidden="true"
                                />
                            ))}

                            {/* Turnos libres: clic para agendar (con teclado se usa el botón "Nueva cita"). */}
                            {canManage &&
                                slots.map((m) => (
                                    <button
                                        key={`slot-${m}`}
                                        type="button"
                                        tabIndex={-1}
                                        aria-label={`Agendar ${col.label} a las ${fromMinutes(m)}`}
                                        className="absolute inset-x-0 hover:bg-primary/5"
                                        style={{
                                            top: (m - opens) * PX_PER_MINUTE,
                                            height: slot * PX_PER_MINUTE,
                                        }}
                                        onClick={() => onSlot(col, fromMinutes(m))}
                                    />
                                ))}

                            {/* Bloqueos */}
                            {col.blocks.map((b) => {
                                const range = blockRange(b, col.date, opens, closes);

                                return (
                                    range && (
                                        <div
                                            key={`block-${b.id}`}
                                            className="pointer-events-none absolute inset-x-0 z-10 flex items-start gap-1 overflow-hidden px-1.5 py-1 text-xs text-muted-foreground"
                                            style={{
                                                top: (range.from - opens) * PX_PER_MINUTE,
                                                height: (range.to - range.from) * PX_PER_MINUTE,
                                                background:
                                                    'repeating-linear-gradient(135deg, var(--muted) 0 6px, transparent 6px 12px)',
                                            }}
                                        >
                                            <Ban
                                                className="mt-0.5 size-3 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <span>Bloqueado: {b.reason}</span>
                                        </div>
                                    )
                                );
                            })}

                            {/* Citas */}
                            {visible.map((a) => {
                                const start = splitDateTime(a.starts_at).minutes;
                                const end = splitDateTime(a.ends_at).minutes;
                                const lane = lanes.get(a.id) ?? { lane: 0, lanes: 1 };
                                const done = a.status === 'atendida' || a.status === 'no_asistio';
                                const short = (end - start) * PX_PER_MINUTE < 40;

                                return (
                                    <button
                                        key={a.id}
                                        type="button"
                                        onClick={() => onAppointment(a)}
                                        aria-label={`${splitDateTime(a.starts_at).time} a ${splitDateTime(a.ends_at).time}: ${a.patient.name}, ${a.type.name}, ${a.professional.name}, ${a.room.name}. ${a.status_label}.${a.patient.has_allergies ? ' Paciente con alergias.' : ''}`}
                                        className={cn(
                                            // flex + justify-start: el texto arriba, donde empieza la cita.
                                            'absolute z-20 flex flex-col justify-start overflow-hidden rounded-md border-l-4 px-1.5 py-0.5 text-left text-xs shadow-sm outline-none focus-visible:ring-[3px] focus-visible:ring-ring',
                                            done && 'opacity-60',
                                        )}
                                        style={{
                                            top: (start - opens) * PX_PER_MINUTE + 1,
                                            height: Math.max((end - start) * PX_PER_MINUTE - 2, 18),
                                            left: `calc(${(lane.lane / lane.lanes) * 100}% + 2px)`,
                                            width: `calc(${100 / lane.lanes}% - 4px)`,
                                            borderLeftColor: a.type.color,
                                            background: `color-mix(in oklab, ${a.type.color} 14%, var(--background))`,
                                        }}
                                    >
                                        <span
                                            className={cn(
                                                'flex items-center gap-1 font-medium',
                                                a.status === 'no_asistio' && 'line-through',
                                            )}
                                        >
                                            {a.patient.has_allergies && (
                                                <AlertTriangle
                                                    className="size-3 shrink-0 text-red-600"
                                                    aria-hidden="true"
                                                />
                                            )}
                                            {a.status === 'atendida' && (
                                                <Check
                                                    className="size-3 shrink-0"
                                                    aria-hidden="true"
                                                />
                                            )}
                                            <span className="truncate">{a.patient.name}</span>
                                        </span>
                                        {!short && (
                                            <span className="block truncate text-muted-foreground">
                                                {splitDateTime(a.starts_at).time} · {a.type.name}
                                            </span>
                                        )}
                                        {!short && a.status === 'confirmada' && (
                                            <span className="block truncate text-muted-foreground">
                                                Confirmada
                                            </span>
                                        )}
                                    </button>
                                );
                            })}

                            {col.date === today && now >= opens && now <= closes && (
                                <div
                                    className="pointer-events-none absolute inset-x-0 z-30 border-t-2 border-red-500"
                                    style={{ top: (now - opens) * PX_PER_MINUTE }}
                                    aria-hidden="true"
                                >
                                    <span className="absolute -top-1 -left-1 size-2 rounded-full bg-red-500" />
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
