import { cn } from '@/lib/utils';
import type { PatientAlertsData } from '@/types';
import { AlertTriangle, ShieldCheck } from 'lucide-react';

/**
 * Alertas clínicas (alergias y condiciones de riesgo). Se muestra en todas las
 * pantallas del paciente. Distingue "sin alertas registradas" de "sin permiso".
 */
export function ClinicalAlertBanner({ alerts }: { alerts: PatientAlertsData | null }) {
    if (alerts === null) {
        return null;
    }

    if (alerts.items.length === 0) {
        return (
            <p
                className="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm text-muted-foreground"
                data-testid="sin-alertas"
            >
                <ShieldCheck className="size-4" aria-hidden="true" />
                Sin alergias ni condiciones de riesgo registradas.
            </p>
        );
    }

    const critical = alerts.items.filter((a) => a.level === 'critical');
    const warnings = alerts.items.filter((a) => a.level === 'warning');

    return (
        <section
            aria-label="Alertas clínicas"
            className={cn(
                'rounded-lg border-2 p-3',
                critical.length > 0
                    ? 'border-red-600 bg-red-50 text-red-950 dark:border-red-500 dark:bg-red-950/40 dark:text-red-50'
                    : 'border-amber-500 bg-amber-50 text-amber-950 dark:bg-amber-950/30 dark:text-amber-50',
            )}
        >
            <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold tracking-wide uppercase">
                <AlertTriangle className="size-4" aria-hidden="true" />
                {alerts.has_allergies
                    ? 'Atención: paciente con alergias'
                    : 'Atención: condiciones de riesgo'}
            </h2>
            <ul className="flex flex-wrap gap-2">
                {[...critical, ...warnings].map((item, index) => (
                    <li
                        key={`${item.label}-${index}`}
                        className={cn(
                            'rounded-md px-2.5 py-1 text-sm',
                            item.level === 'critical'
                                ? 'bg-red-600 font-semibold text-white dark:bg-red-600'
                                : 'bg-amber-200 text-amber-950 dark:bg-amber-800 dark:text-amber-50',
                        )}
                    >
                        {item.label}
                        {item.detail && (
                            <span className="font-normal opacity-90"> — {item.detail}</span>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}
