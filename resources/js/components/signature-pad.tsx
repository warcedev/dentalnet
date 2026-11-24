import { Button } from '@/components/ui/button';
import { Eraser } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Point = { x: number; y: number };

/**
 * Recuadro de firma manuscrita (dedo, lápiz o mouse) que entrega un PNG en
 * data URL. Funciona con eventos pointer, así que sirve en tablet y escritorio.
 */
export function SignaturePad({
    onChange,
    invalid = false,
    describedBy,
    height = 200,
}: {
    onChange: (dataUrl: string | null) => void;
    invalid?: boolean;
    describedBy?: string;
    height?: number;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const last = useRef<Point | null>(null);
    const [hasInk, setHasInk] = useState(false);

    const context = useCallback(() => canvasRef.current?.getContext('2d') ?? null, []);

    const setup = useCallback(() => {
        const canvas = canvasRef.current;
        const ctx = context();

        if (!canvas || !ctx) {
            return;
        }

        const ratio = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        canvas.width = Math.max(1, Math.floor(rect.width * ratio));
        canvas.height = Math.max(1, Math.floor(height * ratio));
        ctx.scale(ratio, ratio);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = 2.5;
        ctx.strokeStyle = '#111827';
    }, [context, height]);

    useEffect(() => {
        setup();
    }, [setup]);

    const position = (event: React.PointerEvent<HTMLCanvasElement>): Point => {
        const rect = event.currentTarget.getBoundingClientRect();

        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    };

    const start = (event: React.PointerEvent<HTMLCanvasElement>) => {
        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        drawing.current = true;
        last.current = position(event);
    };

    const move = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const ctx = context();

        if (!drawing.current || !ctx || !last.current) {
            return;
        }

        const point = position(event);
        ctx.beginPath();
        ctx.moveTo(last.current.x, last.current.y);
        ctx.lineTo(point.x, point.y);
        ctx.stroke();
        last.current = point;

        if (!hasInk) {
            setHasInk(true);
        }
    };

    const end = () => {
        if (!drawing.current) {
            return;
        }

        drawing.current = false;
        last.current = null;

        if (hasInk && canvasRef.current) {
            onChange(canvasRef.current.toDataURL('image/png'));
        }
    };

    const clear = () => {
        const canvas = canvasRef.current;
        const ctx = context();

        if (canvas && ctx) {
            ctx.save();
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.restore();
        }

        setHasInk(false);
        onChange(null);
    };

    return (
        <div className="grid gap-2">
            <div
                className={
                    'relative overflow-hidden rounded-md border-2 border-dashed bg-white ' +
                    (invalid ? 'border-destructive' : 'border-input')
                }
            >
                <canvas
                    ref={canvasRef}
                    role="img"
                    aria-label="Recuadro de firma. Firme con el dedo, un lápiz táctil o el mouse."
                    aria-describedby={describedBy}
                    style={{ height, width: '100%', touchAction: 'none' }}
                    className="block cursor-crosshair"
                    onPointerDown={start}
                    onPointerMove={move}
                    onPointerUp={end}
                    onPointerLeave={end}
                    onPointerCancel={end}
                />
                {!hasInk && (
                    <span className="pointer-events-none absolute inset-x-0 bottom-3 text-center text-sm text-gray-500">
                        Firme aquí
                    </span>
                )}
                <div className="pointer-events-none absolute inset-x-6 bottom-10 border-b border-gray-300" />
            </div>
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground" aria-live="polite">
                    {hasInk ? 'Firma capturada.' : 'Todavía no hay firma.'}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={clear}
                    disabled={!hasInk}
                >
                    <Eraser aria-hidden="true" />
                    Borrar firma
                </Button>
            </div>
        </div>
    );
}
