import { monthGrid, splitDateTime, todayLocal, WEEKDAYS, WEEKDAYS_SHORT } from '@/lib/agenda';
import { cn } from '@/lib/utils';
import type { AgendaAppointment } from '@/types';

const MAX_PER_DAY = 3;

export function MonthView({
    date,
    appointments,
    workingDays,
    onDay,
    onAppointment,
}: {
    date: string;
    appointments: AgendaAppointment[];
    workingDays: number[];
    onDay: (day: string) => void;
    onAppointment: (a: AgendaAppointment) => void;
}) {
    const weeks = monthGrid(date);
    const month = date.slice(0, 7);
    const today = todayLocal();
    const byDay = new Map<string, AgendaAppointment[]>();

    for (const a of appointments.filter((x) => x.status !== 'cancelada')) {
        const day = splitDateTime(a.starts_at).date;
        byDay.set(day, [...(byDay.get(day) ?? []), a]);
    }

    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full min-w-[42rem] table-fixed border-collapse text-sm">
                <caption className="sr-only">Citas del mes</caption>
                <thead>
                    <tr>
                        {WEEKDAYS_SHORT.map((d, i) => (
                            <th
                                key={d}
                                scope="col"
                                className="border-b bg-muted/40 px-2 py-2 text-xs font-medium text-muted-foreground uppercase"
                            >
                                <abbr title={WEEKDAYS[i]} className="no-underline">
                                    {d}
                                </abbr>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {weeks.map((week) => (
                        <tr key={week[0]}>
                            {week.map((day, i) => {
                                const items = byDay.get(day) ?? [];
                                const outside = day.slice(0, 7) !== month;
                                const closed = !workingDays.includes(i + 1);

                                return (
                                    <td
                                        key={day}
                                        className={cn(
                                            'h-28 border-t border-l align-top first:border-l-0',
                                            (outside || closed) && 'bg-muted/30',
                                        )}
                                    >
                                        <div className="flex h-full flex-col gap-0.5 p-1">
                                            <button
                                                type="button"
                                                onClick={() => onDay(day)}
                                                className={cn(
                                                    'self-start rounded px-1.5 text-xs font-medium tabular-nums hover:bg-accent',
                                                    outside && 'text-muted-foreground',
                                                    day === today &&
                                                        'bg-primary text-primary-foreground hover:bg-primary/90',
                                                )}
                                                aria-label={`Ver el día ${day.split('-').reverse().join('/')}${items.length ? `, ${items.length} citas` : ''}`}
                                            >
                                                {Number(day.slice(8))}
                                            </button>
                                            {items.slice(0, MAX_PER_DAY).map((a) => (
                                                <button
                                                    key={a.id}
                                                    type="button"
                                                    onClick={() => onAppointment(a)}
                                                    className="truncate rounded border-l-2 px-1 text-left text-xs hover:bg-accent"
                                                    style={{ borderLeftColor: a.type.color }}
                                                >
                                                    <span className="tabular-nums">
                                                        {splitDateTime(a.starts_at).time}
                                                    </span>{' '}
                                                    {a.patient.name}
                                                </button>
                                            ))}
                                            {items.length > MAX_PER_DAY && (
                                                <button
                                                    type="button"
                                                    onClick={() => onDay(day)}
                                                    className="text-left text-xs text-primary hover:underline"
                                                >
                                                    +{items.length - MAX_PER_DAY} más
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
