import { Form, Head, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useRef, useState } from 'react';
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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { cn } from '@/lib/utils';
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

/** What the side panel works on: a class of the schedule, or a new one on a day. */
type SlotTarget = { slot: Slot } | { weekday: string };

const NO_INSTRUCTOR = 'none';

const ROOM_RULE =
    'Una sola sala: dos clases del mismo día no se pueden cruzar.';

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

    // Opening the dialog again starts from the saved value, not from a
    // change that was never saved.
    function onOpenChange(next: boolean) {
        if (next) {
            setIsActive(classType.isActive);
        }

        setOpen(next);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
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

function AddClassTypeDialog({ slug }: { slug: string }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus />
                    Agregar tipo de clase
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Agregar tipo de clase</DialogTitle>
                <DialogDescription>
                    La duración y el cupo valen para todas las clases de este
                    tipo en el horario.
                </DialogDescription>
                <Form
                    {...storeClassType.form(slug)}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) => focusFirstError(errors)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ClassTypeFields errors={errors} />
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Agregar
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
    weekday,
    weekdays,
    classTypes,
    teachers,
}: {
    errors: Errors;
    slot?: Slot;
    /** The day a new class starts on. */
    weekday: string;
    weekdays: Option[];
    classTypes: ClassType[];
    teachers: Option[];
}) {
    const [instructor, setInstructor] = useState(
        slot?.instructorId ? String(slot.instructorId) : NO_INSTRUCTOR,
    );

    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="weekday">Día</Label>
                <Select
                    name="weekday"
                    defaultValue={slot ? String(slot.weekday) : weekday}
                    required
                >
                    <SelectTrigger
                        id="weekday"
                        {...fieldErrorProps('weekday', errors.weekday)}
                        className="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {weekdays.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError id="weekday-error" message={errors.weekday} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="starts_at">Hora</Label>
                <Input
                    id="starts_at"
                    {...fieldErrorProps('starts_at', errors.starts_at)}
                    name="starts_at"
                    type="time"
                    step={300}
                    defaultValue={slot?.startsAt ?? '19:00'}
                    required
                />
                <InputError id="starts_at-error" message={errors.starts_at} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="class_type_id">Clase</Label>
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
                        id="class_type_id"
                        {...fieldErrorProps(
                            'class_type_id',
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
                    id="class_type_id-error"
                    message={errors.class_type_id}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="instructor_id">Instructor</Label>
                {/* Radix Select has no empty value, so «sin instructor» goes as ''. */}
                <input
                    type="hidden"
                    name="instructor_id"
                    value={instructor === NO_INSTRUCTOR ? '' : instructor}
                />
                <Select value={instructor} onValueChange={setInstructor}>
                    <SelectTrigger
                        id="instructor_id"
                        {...fieldErrorProps(
                            'instructor_id',
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
                    id="instructor_id-error"
                    message={errors.instructor_id}
                />
            </div>
        </div>
    );
}

/**
 * The side panel to add a class to the schedule or change one, so the week
 * stays in sight behind it.
 */
function SlotSheet({
    slug,
    target,
    open,
    onOpenChange,
    onCloseAutoFocus,
    weekdays,
    classTypes,
    teachers,
}: {
    slug: string;
    target: SlotTarget;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCloseAutoFocus: (event: Event) => void;
    weekdays: Option[];
    classTypes: ClassType[];
    teachers: Option[];
}) {
    const slot = 'slot' in target ? target.slot : undefined;
    const dayLabel = (value: number) =>
        weekdays
            .find((weekday) => weekday.value === String(value))
            ?.label.toLowerCase();

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                className="overflow-y-auto"
                onCloseAutoFocus={onCloseAutoFocus}
            >
                <SheetHeader>
                    <SheetTitle>
                        {slot
                            ? `${slot.classTypeName} · ${dayLabel(slot.weekday)} ${slot.startsAt}`
                            : 'Agregar al horario'}
                    </SheetTitle>
                    <SheetDescription>
                        {slot
                            ? 'Las clases que ya tienen reservas quedan como estaban; el cambio rige desde las siguientes.'
                            : ROOM_RULE}
                    </SheetDescription>
                </SheetHeader>

                <Form
                    {...(slot
                        ? updateSlot.form([slug, slot.id])
                        : storeSlot.form(slug))}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    onError={(errors) => focusFirstError(errors)}
                    className="grid gap-6 px-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <SlotFields
                                errors={errors}
                                slot={slot}
                                weekday={
                                    'weekday' in target ? target.weekday : '1'
                                }
                                weekdays={weekdays}
                                // A class keeps its type in the list even if that
                                // type is no longer taught.
                                classTypes={classTypes.filter(
                                    (classType) =>
                                        classType.isActive ||
                                        classType.id === slot?.classTypeId,
                                )}
                                teachers={teachers}
                            />
                            <div>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {slot ? 'Guardar' : 'Agregar al horario'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                {/* Outside the form above: the confirmation has a form of its own. */}
                {slot && (
                    <SheetFooter>
                        <Separator className="mb-2" />
                        <ConfirmDialog
                            trigger={
                                <Button
                                    variant="ghost"
                                    className="justify-start"
                                >
                                    Quitar del horario
                                </Button>
                            }
                            title="¿Quitar esta clase del horario?"
                            description="Dejan de generarse sus clases. Las que ya tienen reservas se mantienen."
                            action={destroySlot.form([slug, slot.id])}
                            confirmLabel="Quitar del horario"
                        />
                    </SheetFooter>
                )}
            </SheetContent>
        </Sheet>
    );
}

function SlotButton({
    slot,
    dayLabel,
    isTypeActive,
    onSelect,
}: {
    slot: Slot;
    dayLabel: string;
    isTypeActive: boolean;
    onSelect: () => void;
}) {
    return (
        <Button
            variant="outline"
            onClick={onSelect}
            aria-label={`Editar ${slot.classTypeName} del ${dayLabel} a las ${slot.startsAt}`}
            className={cn(
                'h-auto w-full flex-col items-start gap-0.5 px-3 py-2 text-left font-normal whitespace-normal',
                // Dashed until it has an instructor: it cannot be booked yet.
                slot.instructorName === null && 'border-dashed',
            )}
        >
            <span className="font-medium tabular-nums">
                {slot.startsAt}–{endTime(slot.startsAt, slot.durationMinutes)}
            </span>
            <span>{slot.classTypeName}</span>
            <span className="text-muted-foreground">
                {slot.instructorName ?? 'Sin instructor'}
            </span>
            {!isTypeActive && (
                <Badge variant="outline" className="mt-1">
                    Ya no se da
                </Badge>
            )}
        </Button>
    );
}

function weeklyCount(count: number): string {
    if (count === 0) {
        return 'Sin clases en el horario';
    }

    return count === 1 ? '1 clase a la semana' : `${count} clases a la semana`;
}

function Count({ value }: { value: number }) {
    return (
        <span className="text-muted-foreground tabular-nums">({value})</span>
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
    const [tab, setTab] = useState(
        classTypes.length === 0 ? 'types' : 'schedule',
    );
    const [target, setTarget] = useState<SlotTarget | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    // The panel has no trigger of its own, so it hands the focus back to the
    // button that opened it.
    const openerRef = useRef<HTMLElement | null>(null);

    if (!studio) {
        return null;
    }

    const activeTypes = classTypes.filter((classType) => classType.isActive);
    const activeTypeIds = new Set(activeTypes.map((classType) => classType.id));
    const days = weekdays.map((weekday) => ({
        ...weekday,
        slots: slots.filter((slot) => String(slot.weekday) === weekday.value),
    }));

    // The target outlives the panel so it can slide out with its content; a
    // class taken off the schedule closes it.
    const isSheetOpen =
        sheetOpen &&
        target !== null &&
        (!('slot' in target) ||
            slots.some((slot) => slot.id === target.slot.id));

    function openSheet(next: SlotTarget) {
        openerRef.current =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        setTarget(next);
        setSheetOpen(true);
    }

    return (
        <>
            <Head title="Clases" />

            <div className="flex flex-col gap-8 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Clases"
                    description={`Las clases que da tu estudio y su horario semanal. De aquí salen las clases que tus alumnas reservan, hasta ${bookingHorizonDays} días antes.`}
                />

                <Tabs value={tab} onValueChange={setTab} className="gap-4">
                    <TabsList variant="line">
                        <TabsTrigger value="schedule">
                            Horario <Count value={slots.length} />
                        </TabsTrigger>
                        <TabsTrigger value="types">
                            Tipos de clase <Count value={classTypes.length} />
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="schedule" className="grid gap-4">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <p className="text-sm text-muted-foreground">
                                {activeTypes.length === 0
                                    ? 'Agrega primero un tipo de clase para armar el horario.'
                                    : ROOM_RULE}
                            </p>
                            {activeTypes.length === 0 ? (
                                <Button
                                    variant="secondary"
                                    onClick={() => setTab('types')}
                                >
                                    Ir a tipos de clase
                                </Button>
                            ) : (
                                <Button
                                    onClick={() => openSheet({ weekday: '1' })}
                                >
                                    <Plus />
                                    Agregar al horario
                                </Button>
                            )}
                        </div>

                        <div className="grid gap-4 xl:grid-cols-7">
                            {days.map((day) => {
                                const dayLabel = day.label.toLowerCase();

                                return (
                                    <section
                                        key={day.value}
                                        aria-labelledby={`day-${day.value}`}
                                        className="grid content-start gap-2 rounded-lg bg-muted/50 p-2"
                                    >
                                        <h2
                                            id={`day-${day.value}`}
                                            className="flex items-baseline justify-between gap-2 px-1 text-sm font-medium"
                                        >
                                            {day.label}
                                            {day.slots.length > 0 && (
                                                <span className="text-muted-foreground tabular-nums">
                                                    {day.slots.length}
                                                </span>
                                            )}
                                        </h2>

                                        {day.slots.length === 0 ? (
                                            <p className="px-1 text-sm text-muted-foreground">
                                                Sin clases
                                            </p>
                                        ) : (
                                            <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-1">
                                                {day.slots.map((slot) => (
                                                    <SlotButton
                                                        key={slot.id}
                                                        slot={slot}
                                                        dayLabel={dayLabel}
                                                        isTypeActive={activeTypeIds.has(
                                                            slot.classTypeId,
                                                        )}
                                                        onSelect={() =>
                                                            openSheet({ slot })
                                                        }
                                                    />
                                                ))}
                                            </div>
                                        )}

                                        {activeTypes.length > 0 && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="justify-start"
                                                aria-label={`Agregar una clase el ${dayLabel}`}
                                                onClick={() =>
                                                    openSheet({
                                                        weekday: day.value,
                                                    })
                                                }
                                            >
                                                <Plus />
                                                Agregar
                                            </Button>
                                        )}
                                    </section>
                                );
                            })}
                        </div>
                    </TabsContent>

                    <TabsContent value="types" className="grid gap-4">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <p className="text-sm text-muted-foreground">
                                {classTypes.length === 0
                                    ? 'Todavía no hay tipos de clase. Agrega el primero, como Pilates mat de 50 minutos para 10 personas.'
                                    : 'Cada clase del horario es de un tipo, con su duración y su cupo.'}
                            </p>
                            <AddClassTypeDialog slug={studio.slug} />
                        </div>

                        {classTypes.length > 0 && (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {classTypes.map((classType) => (
                                    <Card key={classType.id} className="gap-4">
                                        <CardHeader className="flex flex-row items-start justify-between gap-2">
                                            <div className="grid gap-1.5">
                                                <CardTitle>
                                                    <h2>{classType.name}</h2>
                                                </CardTitle>
                                                <CardDescription className="tabular-nums">
                                                    {classType.durationMinutes}{' '}
                                                    min · {classType.capacity}{' '}
                                                    personas
                                                </CardDescription>
                                            </div>
                                            <EditClassTypeDialog
                                                slug={studio.slug}
                                                classType={classType}
                                            />
                                        </CardHeader>
                                        <CardContent className="flex flex-wrap items-center gap-2 text-sm">
                                            <span className="tabular-nums">
                                                {weeklyCount(
                                                    slots.filter(
                                                        (slot) =>
                                                            slot.classTypeId ===
                                                            classType.id,
                                                    ).length,
                                                )}
                                            </span>
                                            {!classType.isActive && (
                                                <Badge variant="outline">
                                                    Ya no se da
                                                </Badge>
                                            )}
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            {target && (
                <SlotSheet
                    slug={studio.slug}
                    target={target}
                    open={isSheetOpen}
                    onOpenChange={setSheetOpen}
                    onCloseAutoFocus={(event) => {
                        event.preventDefault();
                        openerRef.current?.focus();
                    }}
                    weekdays={weekdays}
                    classTypes={classTypes}
                    teachers={teachers}
                />
            )}
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
