import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { SignaturePad } from './signature-pad';

describe('SignaturePad', () => {
    it('describe el recuadro para lectores de pantalla e indica que falta la firma', () => {
        render(<SignaturePad onChange={vi.fn()} describedBy="ayuda" />);

        const canvas = screen.getByRole('img', { name: /Recuadro de firma/ });
        expect(canvas).toHaveAttribute('aria-describedby', 'ayuda');
        expect(screen.getByText('Todavía no hay firma.')).toBeInTheDocument();
    });

    it('el botón de borrar está deshabilitado mientras no hay trazo', () => {
        render(<SignaturePad onChange={vi.fn()} />);

        expect(screen.getByRole('button', { name: 'Borrar firma' })).toBeDisabled();
    });
});
