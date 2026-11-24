import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { EvolutionChart } from './evolution-chart';

const points = [
    { date: '2026-03-01', value: 42.5 },
    { date: '2026-11-20', value: 18 },
];

describe('EvolutionChart', () => {
    it('describe toda la serie para lectores de pantalla y muestra el último valor', () => {
        render(
            <EvolutionChart title="Sangrado al sondaje" unit=" %" minMax={20} points={points} />,
        );

        expect(screen.getByRole('img')).toHaveAccessibleName(
            'Sangrado al sondaje: 01/03/2026 42,5 %; 20/11/2026 18 %',
        );
        expect(screen.getByText('Último: 18 %')).toBeInTheDocument();
    });

    it('muestra el valor al enfocar un punto con el teclado', () => {
        render(<EvolutionChart title="Profundidad media" unit=" mm" minMax={4} points={points} />);

        fireEvent.focus(screen.getByLabelText('01/03/2026: 42,5 mm'));

        expect(screen.getByRole('status')).toHaveTextContent('01/03/2026 42,5 mm');
    });

    it('no dibuja nada sin datos', () => {
        const { container } = render(
            <EvolutionChart title="Vacío" unit="" minMax={1} points={[]} />,
        );

        expect(container).toBeEmptyDOMElement();
    });
});
