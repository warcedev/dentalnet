import { ClinicalAlertBanner } from '@/components/patients/clinical-alert-banner';
import { Badge } from '@/components/ui/badge';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import patients from '@/routes/patients';
import type { PatientHeader } from '@/types';
import { Link } from '@inertiajs/react';
import { Archive } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Marco común de las pantallas del paciente: identificación, alertas
 * clínicas siempre visibles y pestañas según los permisos.
 */
export function PatientLayout({
    header,
    actions,
    children,
}: {
    header: PatientHeader;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const tabs = [
        { title: 'Datos', href: patients.show(header.id), exact: true, visible: true },
        {
            title: 'Ficha clínica',
            href: patients.clinical(header.id),
            exact: false,
            visible: header.can.view_clinical,
        },
        {
            title: 'Odontograma',
            href: patients.odontogram(header.id),
            exact: false,
            visible: header.can.view_odontogram,
        },
        {
            title: 'Periodontograma',
            href: patients.periodontogram(header.id),
            exact: false,
            visible: header.can.view_periodontogram,
        },
        {
            title: 'Consultas',
            href: patients.consultations(header.id),
            exact: false,
            visible: header.can.view_consultations,
        },
        {
            title: 'Tratamientos y pagos',
            href: patients.treatments(header.id),
            exact: false,
            visible: header.can.view_treatments,
        },
        {
            title: 'Documentos',
            href: patients.documents(header.id),
            exact: false,
            visible: header.can.view_documents,
        },
        {
            title: 'Consentimientos',
            href: patients.consents.index(header.id),
            exact: false,
            visible: header.can.view_consents,
        },
    ].filter((t) => t.visible);

    return (
        <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="space-y-1">
                    <h1 className="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight">
                        {header.full_name}
                        {header.is_minor && <Badge variant="secondary">Menor de edad</Badge>}
                        {header.archived && (
                            <Badge variant="destructive">
                                <Archive aria-hidden="true" />
                                Archivado
                            </Badge>
                        )}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {header.document} · {header.age} años ({formatDate(header.birth_date)}) ·{' '}
                        {header.sex}
                        {header.phone && <> · {header.phone}</>}
                    </p>
                </div>
                {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
            </div>

            <ClinicalAlertBanner alerts={header.alerts} />

            <nav aria-label="Secciones del paciente" className="border-b">
                <ul className="-mb-px flex gap-1 overflow-x-auto">
                    {tabs.map((tab) => {
                        const active = tab.exact
                            ? isCurrentUrl(tab.href)
                            : isCurrentOrParentUrl(tab.href);

                        return (
                            <li key={tab.title}>
                                <Link
                                    href={tab.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'inline-flex border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                        active
                                            ? 'border-primary text-foreground'
                                            : 'border-transparent text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {tab.title}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </nav>

            {children}
        </div>
    );
}
