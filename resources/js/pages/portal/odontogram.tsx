import { OdontogramChart, OdontogramLegend } from '@/components/odontogram/odontogram-chart';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { describeTooth } from '@/lib/odontogram';
import type { Chart, Dentition, ToothInfo } from '@/types';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function PortalOdontogram({
    chart,
    teeth,
    age,
}: {
    chart: Chart;
    teeth: ToothInfo[];
    age: number;
}) {
    const dentition: Dentition = age < 6 ? 'temporal' : age <= 12 ? 'mixta' : 'permanente';
    const [selected, setSelected] = useState<number | null>(null);
    const info = useMemo(() => new Map(teeth.map((t) => [t.code, t])), [teeth]);

    return (
        <>
            <Head title="Mi odontograma" />
            <h1 className="text-2xl font-semibold tracking-tight">Mi odontograma</h1>
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        <h2>Estado actual de sus dientes</h2>
                    </CardTitle>
                    <CardDescription>
                        Toque un diente para ver su detalle. Ante cualquier duda, consulte a su
                        odontólogo.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3">
                    <div className="overflow-x-auto rounded-lg border bg-card p-2">
                        <OdontogramChart
                            teeth={teeth}
                            chart={chart}
                            dentition={dentition}
                            selected={selected}
                            selectedSurface={null}
                            onSelect={(code) => setSelected(code)}
                        />
                    </div>
                    <p role="status" className="min-h-5 text-sm">
                        {selected !== null &&
                            describeTooth(selected, info.get(selected), chart[String(selected)])}
                    </p>
                    <OdontogramLegend />
                </CardContent>
            </Card>
        </>
    );
}
