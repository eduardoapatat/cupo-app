import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Check, Clock, TrendingDown, TrendingUp, Wallet } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    close as closeRegister,
    index as cashRegisterIndex,
    store as openRegister,
} from '@/actions/App/Http/Controllers/Studio/CashRegisterController';
import { show as paymentShow } from '@/actions/App/Http/Controllers/Studio/PaymentController';
import InputError from '@/components/input-error';
import PaymentStatusBadge from '@/components/payments/payment-status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { clpDigits, formatClp, formatClpDigits } from '@/lib/money';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CurrentStudio, PaymentRow } from '@/types';

type Totals = { cash: number; card: number; transfer: number };

type Register = {
    id: number;
    personName: string;
    day: string;
    isToday: boolean;
    openedAt: string;
    closedAt: string | null;
    isClosed: boolean;
    openingCash: number;
    totals: Totals;
    expectedCash: number;
    countedCash: number | null;
    /** Counted minus expected: negative when cash is missing. Null while open. */
    difference: number | null;
    closingNote: string | null;
};

type Props = {
    register: (Register & { payments: PaymentRow[] }) | null;
    history: Register[];
    /** Every register of the team, for the owner only. */
    team: Register[] | null;
};

/** «Exacta, sin diferencia», «Faltante de $5.000» or «Sobrante de $2.000». */
function differenceLabel(difference: number): string {
    if (difference === 0) {
        return 'Exacta, sin diferencia';
    }

    return difference < 0
        ? `Faltante de ${formatClp(-difference)}`
        : `Sobrante de ${formatClp(difference)}`;
}

/** The bills and coins of Chile, to count the drawer. */
const DENOMINATIONS = {
    bills: [20000, 10000, 5000, 2000, 1000],
    coins: [500, 100, 50, 10],
} as const;

type Counts = Record<number, string>;

/**
 * An amount of cash, either typed as a total or counted bill by bill: one
 * way or the other. While counting, the total is the sum and is not typed.
 */
function useCashAmount() {
    const [digits, setDigits] = useState('');
    const [counts, setCounts] = useState<Counts>({});
    const [byCount, setByCount] = useState(false);

    return {
        digits,
        counts,
        byCount,
        type(text: string) {
            setDigits(clpDigits(text));
        },
        countByBill(next: boolean) {
            setByCount(next);
            setCounts({});
            setDigits('');
        },
        count(denomination: number, text: string) {
            const next = { ...counts, [denomination]: clpDigits(text) };
            const total = Object.entries(next).reduce(
                (sum, [value, quantity]) =>
                    sum + Number(value) * Number(quantity || 0),
                0,
            );
            setCounts(next);
            setDigits(total === 0 ? '' : String(total));
        },
        reset() {
            setDigits('');
            setCounts({});
            setByCount(false);
        },
    };
}

/**
 * The amount in pesos: «$» before it, «CLP» after it and its thousands
 * dots as she types. The form gets the plain number.
 */
function CashAmountField({
    id,
    name,
    label,
    amount,
    error,
    describedBy = [],
    children,
}: {
    id: string;
    name: string;
    label: string;
    amount: ReturnType<typeof useCashAmount>;
    error?: string;
    describedBy?: string[];
    children?: ReactNode;
}) {
    return (
        <Field data-invalid={error ? true : undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <InputGroup className={cn('h-12', amount.byCount && 'bg-muted/50')}>
                <InputGroupAddon>
                    <InputGroupText className="text-lg">$</InputGroupText>
                </InputGroupAddon>
                <InputGroupInput
                    id={id}
                    {...fieldErrorProps(id, error, ...describedBy)}
                    inputMode="numeric"
                    autoComplete="off"
                    required
                    placeholder="0"
                    readOnly={amount.byCount}
                    value={formatClpDigits(amount.digits)}
                    onChange={(event) => amount.type(event.target.value)}
                    className="text-xl font-semibold tabular-nums read-only:cursor-default md:text-xl"
                />
                <InputGroupAddon align="inline-end">
                    <InputGroupText>CLP</InputGroupText>
                </InputGroupAddon>
            </InputGroup>
            <input type="hidden" name={name} value={amount.digits} />
            {children}
            <FieldError id={`${id}-error`}>{error}</FieldError>
        </Field>
    );
}

/**
 * Count the drawer bill by bill and coin by coin, once she ticks it; the
 * total above then shows the sum and cannot be typed.
 */
function CashCount({
    idPrefix,
    amount,
}: {
    idPrefix: string;
    amount: ReturnType<typeof useCashAmount>;
}) {
    const groups = [
        { legend: 'Billetes', values: DENOMINATIONS.bills },
        { legend: 'Monedas', values: DENOMINATIONS.coins },
    ];

    return (
        <div className="grid gap-4">
            <Field orientation="horizontal">
                <Checkbox
                    id={`${idPrefix}-by-bill`}
                    checked={amount.byCount}
                    onCheckedChange={(checked) =>
                        amount.countByBill(checked === true)
                    }
                />
                <FieldLabel
                    htmlFor={`${idPrefix}-by-bill`}
                    className="font-normal"
                >
                    Contar por billete y moneda
                </FieldLabel>
            </Field>
            {amount.byCount && (
                <div className="grid gap-4">
                    {groups.map((group) => (
                        <FieldSet key={group.legend} className="gap-2">
                            <FieldLegend variant="label">
                                {group.legend}
                            </FieldLegend>
                            <FieldGroup className="grid grid-cols-2 gap-2">
                                {group.values.map((value) => {
                                    const id = `${idPrefix}-${value}`;

                                    return (
                                        <InputGroup key={value}>
                                            <InputGroupAddon>
                                                <label
                                                    htmlFor={id}
                                                    className="min-w-14 text-left tabular-nums"
                                                >
                                                    {formatClp(value)}
                                                </label>
                                                <span aria-hidden="true">
                                                    ×
                                                </span>
                                            </InputGroupAddon>
                                            <InputGroupInput
                                                id={id}
                                                inputMode="numeric"
                                                autoComplete="off"
                                                placeholder="0"
                                                value={
                                                    amount.counts[value] ?? ''
                                                }
                                                onChange={(event) =>
                                                    amount.count(
                                                        value,
                                                        event.target.value,
                                                    )
                                                }
                                                className="tabular-nums"
                                            />
                                        </InputGroup>
                                    );
                                })}
                            </FieldGroup>
                        </FieldSet>
                    ))}
                    <p className="flex justify-between border-t pt-3 text-sm">
                        <span className="text-muted-foreground">
                            Total contado
                        </span>
                        <span className="font-medium tabular-nums">
                            {formatClp(Number(amount.digits || 0))}
                        </span>
                    </p>
                </div>
            )}
        </div>
    );
}

/** Today in words, in the studio's time zone: «Sábado 31 de octubre». */
function todayLabel(): string {
    const label = new Intl.DateTimeFormat('es-CL', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'America/Santiago',
    })
        .format(new Date())
        .replace(',', '');

    return label.charAt(0).toUpperCase() + label.slice(1);
}

/**
 * Open today's register, in a dialog like closing it: the cash she starts
 * with, typed or counted bill by bill.
 */
function OpenRegisterDialog({ slug }: { slug: string }) {
    const [open, setOpen] = useState(false);
    const amount = useCashAmount();

    function onOpenChange(next: boolean) {
        if (next) {
            amount.reset();
        }

        setOpen(next);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>
                <Button>Abrir caja</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-md">
                <DialogTitle>Abre tu caja de hoy</DialogTitle>
                <DialogDescription>
                    {todayLabel()}. Cuenta el efectivo con que partes: lo que
                    vendas hoy entra en tu caja.
                </DialogDescription>
                <Form
                    {...openRegister.form(slug)}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) =>
                        focusFirstError(errors, {
                            opening_cash: 'opening-cash',
                        })
                    }
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <CashAmountField
                                id="opening-cash"
                                name="opening_cash"
                                label="Efectivo con que partes"
                                amount={amount}
                                error={errors.opening_cash}
                            >
                                <CashCount
                                    idPrefix="opening-count"
                                    amount={amount}
                                />
                            </CashAmountField>
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || amount.digits === ''
                                    }
                                >
                                    {processing && <Spinner />}
                                    Abrir caja
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CloseRegisterDialog({
    slug,
    register,
}: {
    slug: string;
    register: Register;
}) {
    const [open, setOpen] = useState(false);
    const counted = useCashAmount();
    const difference =
        counted.digits === ''
            ? null
            : Number(counted.digits) - register.expectedCash;

    function onOpenChange(next: boolean) {
        if (next) {
            counted.reset();
        }

        setOpen(next);
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogTrigger asChild>
                <Button>Cerrar caja</Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-md">
                <DialogTitle>
                    {register.isToday
                        ? 'Cerrar tu caja'
                        : `Cerrar la caja del ${register.day}`}
                </DialogTitle>
                <DialogDescription>
                    Cuenta el efectivo que hay en la caja. Después de cerrarla,
                    sus totales no cambian.
                </DialogDescription>
                <Form
                    {...closeRegister.form([slug, register.id])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) =>
                        focusFirstError(errors, {
                            counted_cash: 'counted-cash',
                            closing_note: 'closing-note',
                        })
                    }
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <dl className="grid gap-2 rounded-lg bg-muted/60 p-4 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Efectivo inicial
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatClp(register.openingCash)}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Cobros en efectivo
                                    </dt>
                                    <dd className="tabular-nums">
                                        {formatClp(register.totals.cash)}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4 border-t pt-2 font-medium">
                                    <dt>Debería haber</dt>
                                    <dd className="tabular-nums">
                                        {formatClp(register.expectedCash)}
                                    </dd>
                                </div>
                            </dl>

                            <CashAmountField
                                id="counted-cash"
                                name="counted_cash"
                                label="Efectivo contado"
                                amount={counted}
                                error={errors.counted_cash}
                                describedBy={['counted-cash-difference']}
                            >
                                <p
                                    id="counted-cash-difference"
                                    aria-live="polite"
                                    className={cn(
                                        'text-sm font-medium tabular-nums',
                                        difference === null &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {difference === null
                                        ? 'Escribe lo que contaste, o cuéntalo por billete.'
                                        : differenceLabel(difference)}
                                </p>
                                <CashCount
                                    idPrefix="closing-count"
                                    amount={counted}
                                />
                            </CashAmountField>

                            {difference !== null && difference !== 0 && (
                                <div className="grid gap-2">
                                    <Label htmlFor="closing-note">
                                        Por qué hay diferencia
                                    </Label>
                                    <Input
                                        id="closing-note"
                                        {...fieldErrorProps(
                                            'closing-note',
                                            errors.closing_note,
                                        )}
                                        name="closing_note"
                                        required
                                        maxLength={300}
                                        placeholder="Di vuelto de más a una alumna"
                                    />
                                    <InputError
                                        id="closing-note-error"
                                        message={errors.closing_note}
                                    />
                                </div>
                            )}

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Cerrar caja
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** The strip of totals, as in a payments dashboard. */
function TotalsStrip({ register }: { register: Register | null }) {
    // Before she opens it: no starting cash yet, and nothing taken.
    const tiles = [
        { label: 'Efectivo inicial', value: register?.openingCash ?? null },
        { label: 'Efectivo', value: register?.totals.cash ?? 0 },
        { label: 'Tarjeta', value: register?.totals.card ?? 0 },
        { label: 'Transferencia', value: register?.totals.transfer ?? 0 },
    ];

    return (
        <dl className="grid grid-cols-2 overflow-hidden rounded-xl border sm:grid-cols-4">
            {tiles.map((tile, index) => (
                <div
                    key={tile.label}
                    className={cn(
                        'grid gap-1 p-4',
                        index % 2 === 1 && 'border-l',
                        index >= 2 && 'border-t sm:border-t-0',
                        index === 2 && 'sm:border-l',
                    )}
                >
                    <dt className="text-sm text-muted-foreground">
                        {tile.label}
                    </dt>
                    <dd
                        className={cn(
                            'text-2xl font-semibold tabular-nums',
                            register === null && 'text-muted-foreground',
                        )}
                    >
                        {tile.value === null ? '—' : formatClp(tile.value)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/** What was counted when it was closed. */
function ClosingSummary({ register }: { register: Register }) {
    if (!register.isClosed || register.difference === null) {
        return null;
    }

    return (
        <dl className="grid gap-2 rounded-xl border p-4 text-sm sm:max-w-md">
            <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Debería haber</dt>
                <dd className="tabular-nums">
                    {formatClp(register.expectedCash)}
                </dd>
            </div>
            <div className="flex justify-between gap-4">
                <dt className="text-muted-foreground">Contado</dt>
                <dd className="tabular-nums">
                    {formatClp(register.countedCash ?? 0)}
                </dd>
            </div>
            <div className="flex justify-between gap-4 border-t pt-2 font-medium">
                <dt>Diferencia</dt>
                <dd className="tabular-nums">
                    {differenceLabel(register.difference)}
                </dd>
            </div>
            {register.closingNote && (
                <p className="text-muted-foreground">
                    «{register.closingNote}»
                </p>
            )}
        </dl>
    );
}

function PaymentsTable({
    slug,
    payments,
}: {
    slug: string;
    payments: PaymentRow[];
}) {
    if (payments.length === 0) {
        return (
            <p className="rounded-xl border border-dashed px-6 py-8 text-center text-sm text-muted-foreground">
                Todavía no hay cobros. Lo que vendas en «Vender plan» aparece
                aquí.
            </p>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Hora</TableHead>
                        <TableHead>Alumna</TableHead>
                        <TableHead>Medio</TableHead>
                        <TableHead className="text-right">Monto</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {payments.map((payment) => (
                        <TableRow key={payment.id} className="relative">
                            <TableCell className="tabular-nums">
                                {payment.time}
                            </TableCell>
                            <TableCell className="whitespace-normal">
                                {/* The whole row opens the payment. */}
                                <Link
                                    href={paymentShow([slug, payment.id])}
                                    className="block font-medium after:absolute after:inset-0 focus-visible:outline-none after:focus-visible:ring-[3px] after:focus-visible:ring-ring/50 after:focus-visible:ring-inset"
                                >
                                    {payment.studentName}
                                </Link>
                                <span className="block text-muted-foreground">
                                    {payment.planName}
                                </span>
                                {payment.status !== 'approved' && (
                                    <span className="mt-1 block">
                                        <PaymentStatusBadge payment={payment} />
                                    </span>
                                )}
                            </TableCell>
                            <TableCell>{payment.method}</TableCell>
                            <TableCell
                                className={cn(
                                    'text-right font-medium tabular-nums',
                                    payment.status !== 'approved' &&
                                        'text-muted-foreground line-through',
                                )}
                            >
                                {formatClp(payment.amount)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

/** Closed registers, one per row, with what they took in and the difference. */
/** The difference with its sign: «−$2.000», «+$1.000» or «$0». */
function signedClp(difference: number): string {
    if (difference === 0) {
        return formatClp(0);
    }

    return difference < 0
        ? `−${formatClp(-difference)}`
        : `+${formatClp(difference)}`;
}

/**
 * How a closed register came out, at a glance: exact (teal), short or
 * over (outlined, with an arrow). Never red: it is a count, not an error.
 */
function ResultBadge({ difference }: { difference: number | null }) {
    if (difference === null) {
        return (
            <Badge variant="outline">
                <Clock aria-hidden="true" />
                Abierta
            </Badge>
        );
    }

    if (difference === 0) {
        return (
            <Badge>
                <Check aria-hidden="true" />
                Exacta
            </Badge>
        );
    }

    return difference < 0 ? (
        <Badge variant="outline" className="border-foreground/40">
            <TrendingDown aria-hidden="true" />
            Faltante
        </Badge>
    ) : (
        <Badge variant="outline" className="border-foreground/40">
            <TrendingUp aria-hidden="true" />
            Sobrante
        </Badge>
    );
}

/**
 * Closed registers, each with the figures of its close: what was taken,
 * the cash there should have been, the cash counted, the difference and
 * its result, with the note of whoever closed it.
 */
function RegistersTable({
    registers,
    showPerson,
}: {
    registers: Register[];
    showPerson: boolean;
}) {
    return (
        <div className="overflow-hidden rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Día</TableHead>
                        {showPerson && (
                            <TableHead className="hidden sm:table-cell">
                                Quién
                            </TableHead>
                        )}
                        <TableHead className="hidden text-right sm:table-cell">
                            Cobrado
                        </TableHead>
                        <TableHead className="hidden text-right md:table-cell">
                            Efectivo esperado
                        </TableHead>
                        <TableHead className="hidden text-right md:table-cell">
                            Contado
                        </TableHead>
                        <TableHead className="text-right">Diferencia</TableHead>
                        <TableHead>Resultado</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {registers.map((register) => (
                        <TableRow key={register.id} className="align-top">
                            <TableCell className="whitespace-normal">
                                <span className="block first-letter:uppercase">
                                    {register.day}
                                </span>
                                {showPerson && (
                                    <span className="block text-muted-foreground sm:hidden">
                                        {register.personName}
                                    </span>
                                )}
                                {register.closingNote && (
                                    <span className="mt-0.5 block max-w-72 text-muted-foreground">
                                        Nota: «{register.closingNote}»
                                    </span>
                                )}
                            </TableCell>
                            {showPerson && (
                                <TableCell className="hidden sm:table-cell">
                                    {register.personName}
                                </TableCell>
                            )}
                            <TableCell className="hidden text-right tabular-nums sm:table-cell">
                                {formatClp(
                                    register.totals.cash +
                                        register.totals.card +
                                        register.totals.transfer,
                                )}
                            </TableCell>
                            <TableCell className="hidden text-right tabular-nums md:table-cell">
                                {formatClp(register.expectedCash)}
                            </TableCell>
                            <TableCell className="hidden text-right tabular-nums md:table-cell">
                                {register.countedCash === null
                                    ? '—'
                                    : formatClp(register.countedCash)}
                            </TableCell>
                            <TableCell
                                className={cn(
                                    'text-right tabular-nums',
                                    register.difference !== null &&
                                        register.difference !== 0 &&
                                        'font-semibold',
                                )}
                            >
                                {register.difference === null
                                    ? '—'
                                    : signedClp(register.difference)}
                            </TableCell>
                            <TableCell>
                                <ResultBadge difference={register.difference} />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

function MyRegister({
    slug,
    register,
}: {
    slug: string;
    register: Props['register'];
}) {
    return (
        <div className="grid gap-6">
            {register === null ? (
                <>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Badge variant="outline">Cerrada</Badge>
                            <span>{todayLabel()}</span>
                        </div>
                        <OpenRegisterDialog slug={slug} />
                    </div>

                    <TotalsStrip register={null} />

                    <section
                        aria-labelledby="register-payments"
                        className="grid gap-3"
                    >
                        <h2
                            id="register-payments"
                            className="text-sm font-medium"
                        >
                            Cobros
                        </h2>
                        <Empty className="border md:p-10">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Wallet />
                                </EmptyMedia>
                                <EmptyTitle>
                                    Todavía no abres tu caja
                                </EmptyTitle>
                                <EmptyDescription>
                                    Ábrela con el efectivo con que partes para
                                    empezar a cobrar. Lo que vendas hoy entra en
                                    ella.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </section>
                </>
            ) : (
                <>
                    {!register.isClosed && !register.isToday && (
                        <Alert>
                            <Wallet />
                            <AlertTitle className="line-clamp-none">
                                Tu caja del {register.day} sigue abierta
                            </AlertTitle>
                            <AlertDescription>
                                Ciérrala contando el efectivo para abrir la de
                                hoy y volver a vender.
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Badge
                                variant={
                                    register.isClosed ? 'outline' : 'secondary'
                                }
                            >
                                {register.isClosed ? 'Cerrada' : 'Abierta'}
                            </Badge>
                            <span className="first-letter:uppercase">
                                {register.isClosed
                                    ? `${register.day}, de ${register.openedAt} a ${register.closedAt}`
                                    : `${register.isToday ? 'Desde' : `${register.day}, desde`} las ${register.openedAt}`}
                            </span>
                        </div>
                        {!register.isClosed && (
                            <CloseRegisterDialog
                                slug={slug}
                                register={register}
                            />
                        )}
                    </div>

                    <TotalsStrip register={register} />
                    <ClosingSummary register={register} />

                    <section
                        aria-labelledby="register-payments"
                        className="grid gap-3"
                    >
                        <h2
                            id="register-payments"
                            className="text-sm font-medium"
                        >
                            Cobros
                        </h2>
                        <PaymentsTable
                            slug={slug}
                            payments={register.payments}
                        />
                    </section>
                </>
            )}
        </div>
    );
}

export default function CashRegister({ register, history, team }: Props) {
    const { studio } = usePage().props;

    if (!studio) {
        return null;
    }

    const tabs = [
        { value: 'mine', label: 'Mi caja' },
        { value: 'history', label: 'Anteriores' },
        ...(team === null ? [] : [{ value: 'team', label: 'Equipo' }]),
    ];

    return (
        <>
            <Head title="Caja" />

            <div className="flex max-w-5xl flex-col gap-6 px-4 py-6">
                <header className="grid gap-1">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Caja
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Lo que cobras en el mesón, por medio de pago. Al cerrar,
                        cuentas el efectivo y ves la diferencia.
                    </p>
                </header>

                {/* Today on its own; the past, and the team's, one tab away. */}
                <Tabs defaultValue="mine" className="gap-6">
                    <TabsList variant="line">
                        {tabs.map((tab) => (
                            <TabsTrigger
                                key={tab.value}
                                value={tab.value}
                                className="after:bg-primary"
                            >
                                {tab.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                    <TabsContent value="mine">
                        <MyRegister slug={studio.slug} register={register} />
                    </TabsContent>
                    <TabsContent value="history" className="grid gap-3">
                        <p className="text-sm text-muted-foreground">
                            Tus últimas 10 cajas cerradas, con lo cobrado y su
                            resultado.
                        </p>
                        {history.length === 0 ? (
                            <p className="rounded-xl border border-dashed px-6 py-8 text-center text-sm text-muted-foreground">
                                Todavía no cierras ninguna caja.
                            </p>
                        ) : (
                            <RegistersTable
                                registers={history}
                                showPerson={false}
                            />
                        )}
                    </TabsContent>
                    {team !== null && (
                        <TabsContent value="team" className="grid gap-3">
                            <p className="text-sm text-muted-foreground">
                                Las cajas de los últimos 14 días y las que
                                siguen abiertas.
                            </p>
                            {team.length === 0 ? (
                                <p className="rounded-xl border border-dashed px-6 py-8 text-center text-sm text-muted-foreground">
                                    Nadie ha abierto una caja estos días.
                                </p>
                            ) : (
                                <RegistersTable registers={team} showPerson />
                            )}
                        </TabsContent>
                    )}
                </Tabs>
            </div>
        </>
    );
}

CashRegister.layout = (props: { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [{ title: 'Caja', href: cashRegisterIndex(props.studio.slug) }]
            : []),
    ],
});
