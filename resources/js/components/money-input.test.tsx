import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { MoneyInput } from './money-input';

describe('MoneyInput', () => {
    it('muestra separadores de miles y envía el entero', () => {
        const { container } = render(
            <>
                <label htmlFor="monto">Monto</label>
                <MoneyInput id="monto" name="amount" />
            </>,
        );

        fireEvent.change(screen.getByLabelText('Monto'), { target: { value: '1250000' } });

        expect(screen.getByLabelText('Monto')).toHaveValue('1.250.000');
        expect(container.querySelector('input[type=hidden]')).toHaveValue('1250000');
    });

    it('ignora letras y decimales', () => {
        const { container } = render(
            <>
                <label htmlFor="monto">Monto</label>
                <MoneyInput id="monto" name="amount" defaultValue={500} />
            </>,
        );

        fireEvent.change(screen.getByLabelText('Monto'), { target: { value: '12a,5' } });

        expect(container.querySelector('input[type=hidden]')).toHaveValue('125');
    });
});
