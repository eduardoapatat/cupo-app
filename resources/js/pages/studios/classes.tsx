import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { index as classesIndex } from '@/actions/App/Http/Controllers/Studio/ClassesController';
import {
    store as storeClassType,
    update as updateClassType,
} from '@/actions/App/Http/Controllers/Studio/ClassTypeController';
import {
    destroy as destroySlot,
    store as storeSlot,
    update as updateSlot,
} from '@/actions/App/Http/Controllers/Studio/ScheduleSlotController';
import ConfirmDialog from '@/components/confirm-dialog';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import type { CurrentStudio, Option } from '@/types';

type ClassType = {
    id: number;
    name: string;
    durationMinutes: number;
    capacity: number;
    isActive: boolean;
};

type Slot = {
    id: number;
    weekday: number;
    startsAt: string;
    classTypeId: number;
    classTypeName: string;
    durationMinutes: number;
    instructorId: number | null;
    instructorName: string | null;
};

type Props = {
    classTypes: ClassType[];
    slots: Slot[];
    weekdays: Option[];
    teachers: Option[];
    bookingHorizonDays: number;
};

type Errors = Partial<Record<string, string>>;

const NO_INSTRUCTOR = 'none';

/** 19:00 plus 50 minutes → 19:50, for the time range of a class. */
function endTime(startsAt: string, minutes: number): string {
    const [hours, mins] = startsAt.split(':').map(Number);
    const total = hours * 60 + mins + minutes;

    return `${String(Math.floor(total / 60) % 24).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

function ClassTypeFields({
    errors,
    classType,
    prefix = '',
}: {
    errors: Errors;
    classType?: ClassType;
    prefix?: string;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}name`}>Nombre</Label>
                <Input
                    id={`${prefix}name`}
                    {...fieldErrorProps(`${prefix}name`, errors.name)}
                    name="name"
                    defaultValue={classType?.name}
                    required
                    maxLength={60}
                    placeholder="Pilates mat"
                />
                <InputError id={`${prefix}name-error`} message={errors.name} />
            </div>
            <div className="grid gap-6 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}duration_minutes`}>
                        Duración (minutos)
                    </Label>
                    <Input
                        id={`${prefix}duration_minutes`}
                        {...fieldErrorProps(
                            `${prefix}duration_minutes`,
                            errors.duration_minutes,
                        )}
                        name="duration_minutes"
                        type="number"
                        inputMode="numeric"
                        min={10}
                        max={480}
                        defaultValue={classType?.durationMinutes ?? 50}
                        required
                    />
                    <InputError
                        id={`${prefix}duration_minutes-error`}
                        message={errors.duration_minutes}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}capacity`}>Cupo (personas)</Label>
                    <Input
                        id={`${prefix}capacity`}
                        {...fieldErrorProps(
                            `${prefix}capacity`,
                            errors.capacity,
                        )}
                        name="capacity"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={200}
                        defaultValue={classType?.capacity ?? 10}
                        required
                    />
                    <InputError
                        id={`${prefix}capacity-error`}
                        message={errors.capacity}
                    />
                </div>
            </div>
        </>
    );
}

function EditClassTypeDialog({
    slug,
    classType,
}: {
    slug: string;
    classType: ClassType;
}) {
    const [open, setOpen] = useState(false);
    const [isActive, setIsActive] = useState(classType.isActive);
    const prefix = `class-type-${classType.id}-`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    aria-label={`Editar ${classType.name}`}
                >
                    Editar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Editar {classType.name}</DialogTitle>
                <DialogDescription>
                    Las clases que ya tienen reservas quedan como estaban; el
                    cambio rige desde las siguientes.
                </DialogDescription>
                <Form
                    {...updateClassType.form([slug, classType.id])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ClassTypeFields
                                errors={errors}
                                classType={classType}
                                prefix={prefix}
                            />
                            <div className="flex items-center gap-3">
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value={isActive ? '1' : '0'}
                                />
                                <Checkbox
                                    id={`${prefix}is_active`}
                                    checked={isActive}
                                    onCheckedChange={(checked) =>
                                        setIsActive(checked === true)
                                    }
                                />
                                <Label htmlFor={`${prefix}is_active`}>
                                    Se sigue dando
                                </Label>
                            </div>
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

function SlotFields({
    errors,
    slot,
    prefix = '',
    weekdays,
    classTypes,
    teachers,
}: {
    errors: Errors;
    slot?: Slot;
    prefix?: string;
    weekdays: Option[];
    classTypes: ClassType[];
    teachers: Option[];
}) {
    const [instructor, setInstructor] = useState(
        slot?.instructorId ? String(slot.instructorId) : NO_INSTRUCTOR,
    );

    return (
        <div className="grid gap-6 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}weekday`}>Día</Label>
                <Select
                    name="weekday"
                    defaultValue={String(slot?.weekday ?? 1)}
                    required
                >
                    <SelectTrigger
                        id={`${prefix}weekday`}
                        {...fieldErrorProps(`${prefix}weekday`, errors.weekday)}
                        className="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {weekdays.map((weekday) => (
                            <SelectItem
                                key={weekday.value}
                                value={weekday.value}
                            >
                                {weekday.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError
                    id={`${prefix}weekday-error`}
                    message={errors.weekday}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}starts_at`}>Hora</Label>
                <Input
                    id={`${prefix}starts_at`}
                    {...fieldErrorProps(`${prefix}starts_at`, errors.starts_at)}
                    name="starts_at"
                    type="time"
                    step={300}
                    defaultValue={slot?.startsAt ?? '19:00'}
                    required
                />
                <InputError
                    id={`${prefix}starts_at-error`}
                    message={errors.starts_at}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}class_type_id`}>Clase</Label>
                <Select
                    name="class_type_id"
                    defaultValue={
                        slot
                            ? String(slot.classTypeId)
                            : String(classTypes[0]?.id ?? '')
                    }
                    required
                >
                    <SelectTrigger
                        id={`${prefix}class_type_id`}
                        {...fieldErrorProps(
                            `${prefix}class_type_id`,
                            errors.class_type_id,
                        )}
                        className="w-full"
                    >
                        <SelectValue placeholder="Elige una clase" />
                    </SelectTrigger>
                    <SelectContent>
                        {classTypes.map((classType) => (
                            <SelectItem
                                key={classType.id}
                                value={String(classType.id)}
                            >
                                {classType.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError
                    id={`${prefix}class_type_id-error`}
                    message={errors.class_type_id}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${prefix}instructor_id`}>Instructor</Label>
                {/* Radix Select has no empty value, so «sin instructor» goes as ''. */}
                <input
                    type="hidden"
                    name="instructor_id"
                    value={instructor === NO_INSTRUCTOR ? '' : instructor}
                />
                <Select value={instructor} onValueChange={setInstructor}>
                    <SelectTrigger
                        id={`${prefix}instructor_id`}
                        {...fieldErrorProps(
                            `${prefix}instructor_id`,
                            errors.instructor_id,
                        )}
                        className="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_INSTRUCTOR}>
                            Sin instructor por ahora
                        </SelectItem>
                        {teachers.map((teacher) => (
                            <SelectItem
                                key={teacher.value}
                                value={teacher.value}
                            >
                                {teacher.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError
                    id={`${prefix}instructor_id-error`}
                    message={errors.instructor_id}
                />
            </div>
        </div>
    );
}

function EditSlotDialog({
    slug,
    slot,
    ...options
}: {
    slug: string;
    slot: Slot;
    weekdays: Option[];
    classTypes: ClassType[];
    teachers: Option[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    aria-label={`Editar ${slot.classTypeName} de las ${slot.startsAt}`}
                >
                    Editar
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {slot.classTypeName} de las {slot.startsAt}
                </DialogTitle>
                <DialogDescription>
                    Las clases que ya tienen reservas quedan como estaban; el
                    cambio rige desde las siguientes.
                </DialogDescription>
                <Form
                    {...updateSlot.form([slug, slot.id])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <SlotFields
                                errors={errors}
                                slot={slot}
                                prefix={`slot-${slot.id}-`}
                                {...options}
                            />
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

export default function Classes({
    classTypes,
    slots,
    weekdays,
    teachers,
    bookingHorizonDays,
}: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    const activeTypes = classTypes.filter((classType) => classType.isActive);
    const days = weekdays
        .map((weekday) => ({
            ...weekday,
            slots: slots.filter(
                (slot) => String(slot.weekday) === weekday.value,
            ),
        }))
        .filter((day) => day.slots.length > 0);

    return (
        <>
            <Head title="Clases" />

            <div className="flex flex-col gap-12 px-4 py-6">
                <Heading
                    title="Clases"
                    description={`Las clases que da tu estudio y su horario semanal. De aquí salen las clases que tus alumnas reservan, hasta ${bookingHorizonDays} días antes.`}
                />

                <section className="grid gap-4">
                    <Heading variant="small" title="Tipos de clase" />
                    {classTypes.length > 0 && (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Clase</TableHead>
                                        <TableHead>Duración</TableHead>
                                        <TableHead>Cupo</TableHead>
                                        <TableHead>Estado</TableHead>
                                        <TableHead className="text-right">
                                            <span className="sr-only">
                                                Acciones
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {classTypes.map((classType) => (
                                        <TableRow key={classType.id}>
                                            <TableCell className="font-medium">
                                                {classType.name}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {classType.durationMinutes} min
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {classType.capacity} personas
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        classType.isActive
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {classType.isActive
                                                        ? 'Se da'
                                                        : 'Ya no se da'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <EditClassTypeDialog
                                                    slug={studio.slug}
                                                    classType={classType}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}

                    <Form
                        {...storeClassType.form(studio.slug)}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        onError={(errors) => focusFirstError(errors)}
                        className="grid max-w-xl gap-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <ClassTypeFields errors={errors} />
                                <div>
                                    <Button
                                        type="submit"
                                        variant={
                                            classTypes.length > 0
                                                ? 'secondary'
                                                : 'default'
                                        }
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Agregar tipo de clase
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                <section className="grid gap-4">
                    <Heading
                        variant="small"
                        title="Horario semanal"
                        description="Una sola sala: dos clases del mismo día no se pueden cruzar."
                    />

                    {days.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Todavía no hay clases en el horario.
                        </p>
                    ) : (
                        days.map((day) => (
                            <div key={day.value} className="grid gap-2">
                                <h3 className="text-sm font-medium">
                                    {day.label}
                                </h3>
                                <div className="overflow-x-auto rounded-lg border">
                                    <Table>
                                        <TableBody>
                                            {day.slots.map((slot) => (
                                                <TableRow key={slot.id}>
                                                    <TableCell className="w-32 tabular-nums">
                                                        {slot.startsAt}–
                                                        {endTime(
                                                            slot.startsAt,
                                                            slot.durationMinutes,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="font-medium">
                                                        {slot.classTypeName}
                                                    </TableCell>
                                                    <TableCell>
                                                        {slot.instructorName ?? (
                                                            <Badge variant="outline">
                                                                Sin instructor
                                                            </Badge>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <EditSlotDialog
                                                                slug={
                                                                    studio.slug
                                                                }
                                                                slot={slot}
                                                                weekdays={
                                                                    weekdays
                                                                }
                                                                classTypes={
                                                                    activeTypes
                                                                }
                                                                teachers={
                                                                    teachers
                                                                }
                                                            />
                                                            <ConfirmDialog
                                                                trigger={
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        aria-label={`Quitar ${slot.classTypeName} de las ${slot.startsAt} del horario`}
                                                                    >
                                                                        Quitar
                                                                    </Button>
                                                                }
                                                                title="¿Quitar esta clase del horario?"
                                                                description="Dejan de generarse sus clases. Las que ya tienen reservas se mantienen."
                                                                action={destroySlot.form(
                                                                    [
                                                                        studio.slug,
                                                                        slot.id,
                                                                    ],
                                                                )}
                                                                confirmLabel="Quitar del horario"
                                                            />
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        ))
                    )}

                    {activeTypes.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Agrega primero un tipo de clase para armar el
                            horario.
                        </p>
                    ) : (
                        <Form
                            {...storeSlot.form(studio.slug)}
                            options={{ preserveScroll: true }}
                            onError={(errors) => focusFirstError(errors)}
                            className="grid max-w-xl gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Heading
                                        variant="small"
                                        title="Agregar al horario"
                                    />
                                    <SlotFields
                                        errors={errors}
                                        weekdays={weekdays}
                                        classTypes={activeTypes}
                                        teachers={teachers}
                                    />
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            Agregar al horario
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    )}
                </section>
            </div>
        </>
    );
}

Classes.layout = (props: Props & { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Clases', href: classesIndex(props.studio.slug) }]
            : []),
    ],
});
