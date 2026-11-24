import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ClinicalAlertBanner } from './clinical-alert-banner';

describe('ClinicalAlertBanner', () => {
    it('no muestra nada si el usuario no tiene permiso de ver alertas', () => {
        const { container } = render(<ClinicalAlertBanner alerts={null} />);

        expect(container).toBeEmptyDOMElement();
    });

    it('distingue "sin alertas registradas"', () => {
        render(<ClinicalAlertBanner alerts={{ has_allergies: false, items: [] }} />);

        expect(
            screen.getByText('Sin alergias ni condiciones de riesgo registradas.'),
        ).toBeInTheDocument();
    });

    it('muestra las alergias como región accesible, con las críticas primero', () => {
        render(
            <ClinicalAlertBanner
                alerts={{
                    has_allergies: true,
                    items: [
                        { kind: 'condicion', level: 'warning', label: 'Diabetes', detail: null },
                        {
                            kind: 'alergia',
                            level: 'critical',
                            label: 'Alergia: Penicilina',
                            detail: 'Grave',
                        },
                    ],
                }}
            />,
        );

        const region = screen.getByRole('region', { name: 'Alertas clínicas' });
        expect(within(region).getByRole('heading')).toHaveTextContent(
            'Atención: paciente con alergias',
        );

        const items = within(region).getAllByRole('listitem');
        expect(items[0]).toHaveTextContent('Alergia: Penicilina — Grave');
        expect(items[1]).toHaveTextContent('Diabetes');
    });
});
