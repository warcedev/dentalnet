import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';
import { Head } from '@inertiajs/react';

export default function Appearance() {
    return (
        <>
            <Head title="Apariencia" />

            <h1 className="sr-only">Apariencia</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Apariencia"
                    description="Elija el tema claro, oscuro o el del sistema"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Apariencia',
            href: editAppearance(),
        },
    ],
};
