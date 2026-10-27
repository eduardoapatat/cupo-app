import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { store as resendActivation } from '@/actions/App/Http/Controllers/Studio/StudentActivationController';
import {
    index as studentsIndex,
    store as enrollStudent,
    update as updateStudent,
} from '@/actions/App/Http/Controllers/Studio/StudentController';
import EnrollmentLinkField from '@/components/enrollment-link-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { dashboard } from '@/routes';
import type { CurrentStudio } from '@/types';

type StudentStatus = 'app' | 'activation_sent' | 'no_app';

type Student = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    status: StudentStatus;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    students: Paginated<Student>;
    enrollmentUrl: string;
};

const STATUS_LABELS: Record<StudentStatus, string> = {
    app: 'Con app',
    activation_sent: 'Activación enviada',
    no_app: 'Sin app',
};

/** +56912345678 → +56 9 1234 5678, as people read it. */
function formatPhone(phone: string | null): string {
    const match = phone?.match(/^\+56(9)(\d{4})(\d{4})$/);

    return match ? `+56 ${match[1]} ${match[2]} ${match[3]}` : (phone ?? '');
}

function ContactFields({
    errors,
    student,
}: {
    errors: Partial<Record<string, string>>;
    student?: Student;
}) {
    const prefix = student ? `student-${student.id}-` : '';

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}name`}>Nombre</Label>
                <Input
                    id={`${prefix}name`}
                    {...fieldErrorProps(`${prefix}name`, errors.name)}
                    name="name"
                    defaultValue={student?.name}
                    required
                    autoComplete="off"
                    placeholder="Rosa Fuentes"
                />
                <InputError id={`${prefix}name-error`} message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}phone`}>Celular</Label>
                <Input
                    id={`${prefix}phone`}
                    {...fieldErrorProps(`${prefix}phone`, errors.phone)}
                    name="phone"
                    type="tel"
                    defaultValue={formatPhone(student?.phone ?? null)}
                    required
                    autoComplete="off"
                    placeholder="9 1234 5678"
                />
                <InputError
                    id={`${prefix}phone-error`}
                    message={errors.phone}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}email`}>Correo (opcional)</Label>
                <Input
                    id={`${prefix}email`}
                    {...fieldErrorProps(
                        `${prefix}email`,
                        errors.email,
                        `${prefix}email-help`,
                    )}
                    name="email"
                    type="email"
                    defaultValue={student?.email ?? ''}
                    autoComplete="off"
                    placeholder="correo@ejemplo.com"
                />
                <p
                    id={`${prefix}email-help`}
                    className="text-sm text-muted-foreground"
                >
                    Si lo anotas, le llega un enlace para crear su contraseña y
                    reservar desde el celular.
                </p>
                <InputError
                    id={`${prefix}email-error`}
                    message={errors.email}
                />
            </div>
        </>
    );
}

function EnrollForm({ slug }: { slug: string }) {
    return (
        <Form
            {...enrollStudent.form(slug)}
            resetOnSuccess
            onError={(errors) => focusFirstError(errors)}
            className="grid max-w-xl gap-6"
        >
            {({ processing, errors }) => (
                <>
                    <ContactFields errors={errors} />

                    <div className="grid gap-2">
                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="adult"
                                name="adult"
                                required
                                {...fieldErrorProps('adult', errors.adult)}
                            />
                            <Label htmlFor="adult">
                                Confirmo que es mayor de 18 años
                            </Label>
                        </div>
                        <InputError id="adult-error" message={errors.adult} />
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Inscribir alumna
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function EditStudentDialog({
    slug,
    student,
}: {
    slug: string;
    student: Student;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    aria-label={`Editar los datos de ${student.name}`}
                >
                    Editar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Datos de {student.name}</DialogTitle>
                <DialogDescription>
                    Mientras no use la app, recepción mantiene sus datos al día.
                </DialogDescription>

                <Form
                    {...updateStudent.form([slug, student.id])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) =>
                        focusFirstError(errors, {
                            name: `student-${student.id}-name`,
                            phone: `student-${student.id}-phone`,
                            email: `student-${student.id}-email`,
                        })
                    }
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ContactFields errors={errors} student={student} />
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function StudentRow({ slug, student }: { slug: string; student: Student }) {
    return (
        <TableRow>
            <TableCell className="font-medium">{student.name}</TableCell>
            <TableCell className="tabular-nums">
                {formatPhone(student.phone)}
            </TableCell>
            <TableCell
                className="max-w-64 truncate text-muted-foreground"
                title={student.email ?? undefined}
            >
                {student.email ?? 'Sin correo'}
            </TableCell>
            <TableCell>
                <Badge
                    variant={student.status === 'app' ? 'secondary' : 'outline'}
                >
                    {STATUS_LABELS[student.status]}
                </Badge>
            </TableCell>
            <TableCell className="text-right">
                {student.status !== 'app' && (
                    <div className="flex justify-end gap-2">
                        {student.status === 'activation_sent' && (
                            <Form
                                {...resendActivation.form([slug, student.id])}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        disabled={processing}
                                        aria-label={`Reenviar la activación a ${student.name}`}
                                    >
                                        {processing && <Spinner />}
                                        Reenviar activación
                                    </Button>
                                )}
                            </Form>
                        )}
                        <EditStudentDialog slug={slug} student={student} />
                    </div>
                )}
            </TableCell>
        </TableRow>
    );
}

export default function Students({ students, enrollmentUrl }: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    return (
        <>
            <Head title="Alumnas" />

            <div className="flex flex-col gap-10 px-4 py-6">
                <Heading
                    title="Alumnas"
                    description="Quienes toman clases en el estudio. Las que usan la app manejan sus propios datos."
                />

                <section className="grid max-w-xl gap-4">
                    <Heading
                        variant="small"
                        title="Que se inscriban solas"
                        description="Comparte el enlace del estudio: quien entra por él crea su cuenta y queda como alumna."
                    />
                    <EnrollmentLinkField url={enrollmentUrl} />
                </section>

                <section className="grid gap-4">
                    <Heading
                        variant="small"
                        title="Inscribir en el mesón"
                        description="Para quien no usa la app, como quien paga en efectivo y reserva en el mesón. Basta con su nombre y su celular."
                    />
                    <EnrollForm slug={studio.slug} />
                </section>

                <section className="grid gap-4">
                    <Heading
                        variant="small"
                        title={
                            students.total === 1
                                ? '1 alumna'
                                : `${students.total} alumnas`
                        }
                    />
                    {students.total === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Todavía no hay alumnas. Comparte el enlace o
                            inscribe a la primera en el mesón.
                        </p>
                    ) : (
                        <>
                            <div className="overflow-x-auto rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Nombre</TableHead>
                                            <TableHead>Celular</TableHead>
                                            <TableHead>Correo</TableHead>
                                            <TableHead>Estado</TableHead>
                                            <TableHead className="text-right">
                                                <span className="sr-only">
                                                    Acciones
                                                </span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {students.data.map((student) => (
                                            <StudentRow
                                                key={student.id}
                                                slug={studio.slug}
                                                student={student}
                                            />
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {students.last_page > 1 && (
                                <nav
                                    aria-label="Páginas de alumnas"
                                    className="flex items-center justify-between gap-4 text-sm"
                                >
                                    <span className="text-muted-foreground">
                                        Página {students.current_page} de{' '}
                                        {students.last_page}
                                    </span>
                                    <div className="flex gap-2">
                                        {students.prev_page_url && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={
                                                        students.prev_page_url
                                                    }
                                                    preserveScroll
                                                >
                                                    Anterior
                                                </Link>
                                            </Button>
                                        )}
                                        {students.next_page_url && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={
                                                        students.next_page_url
                                                    }
                                                    preserveScroll
                                                >
                                                    Siguiente
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </nav>
                            )}
                        </>
                    )}
                </section>
            </div>
        </>
    );
}

Students.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Alumnas', href: studentsIndex(props.studio.slug) }]
            : []),
    ],
});
