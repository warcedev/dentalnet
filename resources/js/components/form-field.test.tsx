import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FormField } from './form-field';

describe('FormField', () => {
    it('asocia la etiqueta al control', () => {
        render(
            <FormField id="nombre" label="Nombre">
                {(p) => <input {...p} />}
            </FormField>,
        );

        expect(screen.getByLabelText('Nombre')).toHaveAttribute('id', 'nombre');
    });

    it('anuncia el error junto al campo y marca el control como inválido', () => {
        render(
            <FormField
                id="email"
                label="Correo"
                error="El correo es obligatorio."
                hint="Use el correo laboral"
                required
            >
                {(p) => <input {...p} />}
            </FormField>,
        );

        const input = screen.getByLabelText(/Correo/);
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAttribute('aria-required', 'true');
        expect(input).toHaveAccessibleDescription(
            'Use el correo laboral El correo es obligatorio.',
        );
    });

    it('no marca inválido un campo sin error', () => {
        render(
            <FormField id="tel" label="Teléfono">
                {(p) => <input {...p} />}
            </FormField>,
        );

        expect(screen.getByLabelText('Teléfono')).not.toHaveAttribute('aria-invalid');
    });
});
