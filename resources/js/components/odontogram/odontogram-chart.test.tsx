import type { Chart, ToothInfo } from '@/types';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { OdontogramChart } from './odontogram-chart';

const teeth: ToothInfo[] = [16, 11, 21, 46].map((code) => ({
    code,
    dentition: 'permanente',
    quadrant: Math.floor(code / 10),
    position: code % 10,
    arch: code < 30 ? 'superior' : 'inferior',
    type: code % 10 >= 6 ? 'molar' : 'incisivo',
}));

const chart: Chart = {
    '16': {
        absent: false,
        tooth: [],
        surfaces: {
            oclusal: {
                id: 1,
                tooth_code: 16,
                surface: 'oclusal',
                scope: 'superficie',
                condition: 'caries',
                condition_label: 'Caries',
                status: 'diagnosticado',
                status_label: 'Diagnosticado',
                date: '2026-01-05',
            },
        },
    },
};

function renderChart(onSelect = vi.fn()) {
    render(
        <OdontogramChart
            teeth={teeth}
            chart={chart}
            dentition="permanente"
            selected={null}
            selectedSurface={null}
            onSelect={onSelect}
        />,
    );

    return onSelect;
}

describe('OdontogramChart', () => {
    it('muestra las 32 piezas permanentes con una descripción accesible', () => {
        renderChart();

        const buttons = screen.getAllByRole('button');
        expect(buttons).toHaveLength(32);
        expect(
            screen.getByRole('button', {
                name: 'Diente 16, molar superior: Oclusal: Caries (diagnosticado)',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /^Diente 21, .*sin registros$/ }),
        ).toBeInTheDocument();
    });

    it('tiene un solo punto de tabulación y se recorre con las flechas', async () => {
        renderChart();
        const user = userEvent.setup();

        expect(
            screen.getAllByRole('button').filter((b) => b.getAttribute('tabindex') === '0'),
        ).toHaveLength(1);

        await user.tab();
        expect(document.activeElement).toHaveAttribute('data-tooth', '18');

        await user.keyboard('{ArrowRight}');
        expect(document.activeElement).toHaveAttribute('data-tooth', '17');

        await user.keyboard('{ArrowDown}');
        expect(document.activeElement).toHaveAttribute('data-tooth', '47');
    });

    it('Enter selecciona el diente completo', async () => {
        const onSelect = renderChart();
        const user = userEvent.setup();

        await user.tab();
        await user.keyboard('{Enter}');

        expect(onSelect).toHaveBeenCalledWith(18, null);
    });

    it('un clic en una superficie la selecciona', () => {
        const onSelect = renderChart();
        const tooth = document.querySelector('[data-tooth="16"]')!;

        fireEvent.click(tooth.querySelector('[data-surface="mesial"]')!);

        expect(onSelect).toHaveBeenCalledWith(16, 'mesial');
    });
});
