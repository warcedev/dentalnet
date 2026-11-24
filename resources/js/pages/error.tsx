import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/react';

const MESSAGES: Record<number, { title: string; description: string }> = {
    403: {
        title: 'No tiene permiso para ver esta página',
        description: 'Si cree que debería tener acceso, consulte al administrador del consultorio.',
    },
    404: {
        title: 'Página no encontrada',
        description: 'La dirección no existe o el registro fue archivado.',
    },
    429: {
        title: 'Demasiados intentos',
        description: 'Espere un minuto antes de volver a intentar.',
    },
    500: {
        title: 'Ocurrió un error inesperado',
        description: 'El problema quedó registrado. Intente de nuevo en unos minutos.',
    },
    503: {
        title: 'Sistema en mantenimiento',
        description: 'Volveremos a estar disponibles en breve.',
    },
};

export default function ErrorPage({ status }: { status: number }) {
    const { auth } = usePage().props;
    const message = MESSAGES[status] ?? MESSAGES[500];

    return (
        <>
            <Head title={message.title} />
            <main className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-center">
                <p className="text-6xl font-semibold text-primary" aria-hidden="true">
                    {status}
                </p>
                <div className="max-w-md space-y-2">
                    <h1 className="text-2xl font-semibold">{message.title}</h1>
                    <p className="text-muted-foreground">{message.description}</p>
                </div>
                <Button asChild>
                    <Link href={auth?.user ? dashboard() : '/login'}>
                        {auth?.user ? 'Volver al inicio' : 'Ir a iniciar sesión'}
                    </Link>
                </Button>
            </main>
        </>
    );
}
