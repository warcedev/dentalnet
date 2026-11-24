import type { PatientHeader } from '@/types';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { PatientLayout } from './patient-layout';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string | { url: string };
        children: React.ReactNode;
    }) => (
        <a href={typeof href === 'string' ? href : href.url} {...props}>
            {children}
        </a>
    ),
    usePage: () => ({ url: '/pacientes/7' }),
}));

function header(can: Partial<PatientHeader['can']>): PatientHeader {
    return {
        id: 7,
        full_name: 'Ana Núñez',
        document: 'Cédula de identidad 7654321',
        birth_date: '1990-05-10',
        age: 36,
        is_minor: false,
        sex: 'Femenino',
        phone: null,
        archived: false,
        alerts: null,
        can: {
            update: false,
            archive: false,
            restore: false,
            view_clinical: false,
            view_odontogram: false,
            view_treatments: false,
            view_consultations: false,
            view_documents: false,
            view_periodontogram: false,
            update_clinical: false,
            view_consents: false,
            collect_consent: false,
            ...can,
        },
    };
}

const tabNames = () => screen.getAllByRole('link').map((a) => a.textContent);

describe('PatientLayout', () => {
    it('muestra al odontólogo todas las pestañas, incluido el odontograma', () => {
        render(
            <PatientLayout
                header={header({
                    view_clinical: true,
                    view_odontogram: true,
                    view_consultations: true,
                    view_treatments: true,
                    view_documents: true,
                    view_periodontogram: true,
                    view_consents: true,
                })}
            >
                contenido
            </PatientLayout>,
        );

        expect(tabNames()).toEqual([
            'Datos',
            'Ficha clínica',
            'Odontograma',
            'Periodontograma',
            'Consultas',
            'Tratamientos y pagos',
            'Documentos',
            'Consentimientos',
        ]);
        expect(screen.getByRole('link', { name: 'Odontograma' })).toHaveAttribute(
            'href',
            '/pacientes/7/odontograma',
        );
    });

    it('a recepción le muestra pagos pero no la ficha clínica, el odontograma ni las consultas', () => {
        render(
            <PatientLayout
                header={header({
                    view_consents: true,
                    view_treatments: true,
                    view_documents: true,
                })}
            >
                contenido
            </PatientLayout>,
        );

        expect(tabNames()).toEqual([
            'Datos',
            'Tratamientos y pagos',
            'Documentos',
            'Consentimientos',
        ]);
    });

    it('marca la pestaña actual', () => {
        render(<PatientLayout header={header({ view_odontogram: true })}>contenido</PatientLayout>);

        expect(screen.getByRole('link', { name: 'Datos' })).toHaveAttribute('aria-current', 'page');
    });
});
