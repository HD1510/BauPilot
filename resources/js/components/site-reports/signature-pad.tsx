import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';

/**
 * Unterschriften-Feld (M9): zeichnen per Finger/Stift/Maus, Ergebnis
 * als PNG-Data-URL. Bewusst ohne Fremdpaket — ein Canvas reicht.
 */
export function SignaturePad({
    onChange,
}: {
    onChange: (dataUrl: string | null) => void;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const hasInk = useRef(false);

    useEffect(() => {
        const canvas = canvasRef.current;

        if (!canvas) {
            return;
        }

        // Interne Auflösung an die Anzeigegröße koppeln (scharfe Linien).
        canvas.width = canvas.offsetWidth * 2;
        canvas.height = canvas.offsetHeight * 2;
        const context = canvas.getContext('2d');

        if (context) {
            context.scale(2, 2);
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.strokeStyle = '#0f172a';
        }
    }, []);

    const point = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();

        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    };

    return (
        <div className="grid gap-2">
            <canvas
                ref={canvasRef}
                className="h-36 w-full touch-none rounded-lg border border-sidebar-border/70 bg-white dark:border-sidebar-border"
                onPointerDown={(event) => {
                    event.currentTarget.setPointerCapture(event.pointerId);
                    drawing.current = true;
                    const context = event.currentTarget.getContext('2d');
                    const { x, y } = point(event);
                    context?.beginPath();
                    context?.moveTo(x, y);
                }}
                onPointerMove={(event) => {
                    if (!drawing.current) {
                        return;
                    }

                    const context = event.currentTarget.getContext('2d');
                    const { x, y } = point(event);
                    context?.lineTo(x, y);
                    context?.stroke();
                    hasInk.current = true;
                }}
                onPointerUp={(event) => {
                    drawing.current = false;

                    if (hasInk.current) {
                        onChange(event.currentTarget.toDataURL('image/png'));
                    }
                }}
            />
            <div className="flex items-center justify-between">
                <span className="text-xs text-muted-foreground">
                    Hier unterschreiben
                </span>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        const canvas = canvasRef.current;
                        const context = canvas?.getContext('2d');

                        if (canvas && context) {
                            context.clearRect(0, 0, canvas.width, canvas.height);
                        }

                        hasInk.current = false;
                        onChange(null);
                    }}
                >
                    Löschen
                </Button>
            </div>
        </div>
    );
}
