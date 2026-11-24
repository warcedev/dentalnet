import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Textarea } from '@/components/textarea';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import admin from '@/routes/admin';
import { Form, Head, Link } from '@inertiajs/react';

type Props = {
    template: {
        id: number;
        title: string;
        body: string;
        version: number;
        is_active: boolean;
    } | null;
    placeholders: string[];
};

export default function ConsentTemplateForm({ template, placeholders }: Props) {
    const isEdit = template !== null;
    const title = isEdit ? `Editar "${template.title}"` : 'Nueva plantilla de consentimiento';

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description={
                        isEdit
                            ? `Al guardar se publica la versión ${template.version + 1}. La versión ${template.version} queda guardada para los consentimientos ya firmados.`
                            : 'Redacte el texto que leerá y firmará el paciente.'
                    }
                />

                <Form
                    {...(isEdit
                        ? admin.consents.update.form(template.id)
                        : admin.consents.store.form())}
                    className="grid max-w-3xl gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <FormField id="title" label="Título" error={errors.title} required>
                                {(p) => (
                                    <Input {...p} name="title" defaultValue={template?.title} />
                                )}
                            </FormField>
                            <FormField
                                id="body"
                                label="Texto"
                                error={errors.body}
                                hint={`Puede usar estos marcadores, que se completan al firmar: ${placeholders.join(', ')}`}
                                required
                            >
                                {(p) => (
                                    <Textarea
                                        {...p}
                                        name="body"
                                        rows={18}
                                        defaultValue={template?.body}
                                    />
                                )}
                            </FormField>
                            <p className="text-sm text-muted-foreground">
                                Recomendación: haga revisar los textos por un asesor legal antes de
                                usarlos con pacientes.
                            </p>
                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {isEdit ? 'Publicar nueva versión' : 'Crear plantilla'}
                                </Button>
                                <Button asChild variant="ghost">
                                    <Link href={admin.consents.index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ConsentTemplateForm.layout = {
    breadcrumbs: [{ title: 'Consentimientos', href: admin.consents.index() }],
};
