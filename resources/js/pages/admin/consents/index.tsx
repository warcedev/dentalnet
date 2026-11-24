import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import { Head, Link } from '@inertiajs/react';
import { FileText, Pencil, Plus } from 'lucide-react';

type Template = {
    id: number;
    code: string;
    title: string;
    version: number;
    is_active: boolean;
    updated_at: string | null;
    signatures: number;
    versions: number;
};

export default function ConsentTemplates({ templates }: { templates: Template[] }) {
    return (
        <>
            <Head title="Plantillas de consentimiento" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Plantillas de consentimiento"
                    description="Editar una plantilla publica una versión nueva: lo ya firmado conserva el texto original."
                    actions={
                        <Button asChild>
                            <Link href={admin.consents.create()}>
                                <Plus aria-hidden="true" />
                                Nueva plantilla
                            </Link>
                        </Button>
                    }
                />

                {templates.length === 0 ? (
                    <EmptyState icon={FileText} title="No hay plantillas" />
                ) : (
                    <ul className="grid gap-3">
                        {templates.map((t) => (
                            <li
                                key={t.code}
                                className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p className="flex flex-wrap items-center gap-2 font-medium">
                                        {t.title}
                                        {t.is_active ? (
                                            <Badge variant="outline">Vigente</Badge>
                                        ) : (
                                            <Badge variant="secondary">Inactiva</Badge>
                                        )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Versión {t.version} del {formatDate(t.updated_at)} ·{' '}
                                        {t.versions} {t.versions === 1 ? 'versión' : 'versiones'} ·{' '}
                                        {t.signatures} firmados
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={admin.consents.edit(t.id)}>
                                            <Pencil aria-hidden="true" />
                                            {t.is_active ? 'Editar' : 'Reactivar con cambios'}
                                        </Link>
                                    </Button>
                                    {t.is_active && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="ghost" size="sm">
                                                    Desactivar
                                                </Button>
                                            }
                                            title={`¿Desactivar "${t.title}"?`}
                                            description="No se ofrecerá para firmar. Los consentimientos ya firmados no cambian."
                                            confirmLabel="Desactivar"
                                            form={admin.consents.deactivate.form(t.id)}
                                        />
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

ConsentTemplates.layout = {
    breadcrumbs: [{ title: 'Consentimientos', href: admin.consents.index() }],
};
