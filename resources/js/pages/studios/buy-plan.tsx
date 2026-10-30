import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    create as buyPlan,
    store as buyPlanStore,
} from '@/actions/App/Http/Controllers/Studio/BuyPlanController';
import { index as myPlanIndex } from '@/actions/App/Http/Controllers/Studio/MyPlanController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { fieldErrorProps, focusFirstError } from '@/lib/form';
import { formatClp } from '@/lib/money';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { CurrentStudio } from '@/types';

type BuyablePlan = {
    id: number;
    name: string;
    price: number;
    credits: number | null;
    validityDays: number;
    dailyLimit: number | null;
    /** Its last day if it started today, in words. */
    lastDay: string;
};

type Props = {
    plans: BuyablePlan[];
    /** Where to transfer; null while the studio has not written them. */
    transferDetails: string | null;
};

function classesLabel(credits: number | null): string {
    if (credits === null) {
        return 'Clases ilimitadas';
    }

    return credits === 1 ? '1 clase' : `${credits} clases`;
}

export default function BuyPlan({ plans, transferDetails }: Props) {
    const { studio } = usePage().props;
    const [planId, setPlanId] = useState<string>(
        plans.length === 1 ? String(plans[0].id) : '',
    );

    if (!studio) {
        return null;
    }

    const plan = plans.find((item) => String(item.id) === planId) ?? null;

    return (
        <>
            <Head title="Comprar plan" />

            <div className="flex max-w-5xl flex-col gap-6 px-4 py-6">
                <Heading
                    className="mb-0"
                    title="Comprar plan"
                    description="Elige tu plan, transfiere y sube el comprobante. Tu plan parte cuando el estudio lo valide."
                />

                {transferDetails === null || plans.length === 0 ? (
                    <p className="rounded-xl border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                        Por ahora el estudio vende sus planes solo en el mesón.
                    </p>
                ) : (
                    <Form
                        {...buyPlanStore.form(studio.slug)}
                        onError={(errors) =>
                            focusFirstError(errors, {
                                plan_id: `buy-plan-${plans[0].id}`,
                                receipt: 'buy-receipt',
                            })
                        }
                        className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-8">
                                    <section
                                        aria-labelledby="buy-plan-title"
                                        className="grid gap-3"
                                    >
                                        <h2
                                            id="buy-plan-title"
                                            className="text-sm font-medium"
                                        >
                                            1. Tu plan
                                        </h2>
                                        <RadioGroup
                                            name="plan_id"
                                            value={planId}
                                            onValueChange={setPlanId}
                                            aria-labelledby="buy-plan-title"
                                            className="grid gap-3 sm:grid-cols-2"
                                        >
                                            {plans.map((item) => {
                                                const id = `buy-plan-${item.id}`;

                                                return (
                                                    <Label
                                                        key={item.id}
                                                        htmlFor={id}
                                                        className={cn(
                                                            'flex cursor-pointer items-start gap-3 rounded-xl border p-4 font-normal transition-colors hover:bg-muted/50',
                                                            String(item.id) ===
                                                                planId &&
                                                                'border-foreground bg-muted/40',
                                                        )}
                                                    >
                                                        <RadioGroupItem
                                                            id={id}
                                                            value={String(
                                                                item.id,
                                                            )}
                                                            className="mt-0.5"
                                                        />
                                                        <span className="grid min-w-0 flex-1 gap-0.5">
                                                            <span className="font-medium break-words">
                                                                {item.name}
                                                            </span>
                                                            <span className="text-sm text-muted-foreground">
                                                                {classesLabel(
                                                                    item.credits,
                                                                )}{' '}
                                                                ·{' '}
                                                                {
                                                                    item.validityDays
                                                                }{' '}
                                                                días
                                                            </span>
                                                        </span>
                                                        <span className="font-medium tabular-nums">
                                                            {formatClp(
                                                                item.price,
                                                            )}
                                                        </span>
                                                    </Label>
                                                );
                                            })}
                                        </RadioGroup>
                                        <InputError message={errors.plan_id} />
                                    </section>

                                    <section
                                        aria-labelledby="buy-transfer-title"
                                        className="grid gap-3"
                                    >
                                        <h2
                                            id="buy-transfer-title"
                                            className="text-sm font-medium"
                                        >
                                            2. Transfiere
                                            {plan &&
                                                ` ${formatClp(plan.price)}`}{' '}
                                            a
                                        </h2>
                                        <p className="rounded-xl border bg-muted/40 p-4 text-sm whitespace-pre-line">
                                            {transferDetails}
                                        </p>
                                    </section>

                                    <section className="grid gap-2">
                                        <Label
                                            htmlFor="buy-receipt"
                                            className="text-sm font-medium"
                                        >
                                            3. Sube el comprobante
                                        </Label>
                                        <Input
                                            id="buy-receipt"
                                            {...fieldErrorProps(
                                                'buy-receipt',
                                                errors.receipt,
                                                'buy-receipt-help',
                                            )}
                                            name="receipt"
                                            type="file"
                                            accept="image/jpeg,image/png,application/pdf"
                                            required
                                        />
                                        <p
                                            id="buy-receipt-help"
                                            className="text-sm text-muted-foreground"
                                        >
                                            Una captura o un PDF de hasta 5 MB.
                                        </p>
                                        <InputError
                                            id="buy-receipt-error"
                                            message={errors.receipt}
                                        />
                                    </section>
                                </div>

                                <Card className="lg:sticky lg:top-6">
                                    <CardHeader>
                                        <CardTitle>
                                            <h2>Resumen</h2>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        {plan ? (
                                            <div className="grid gap-1 text-sm">
                                                <span className="font-medium">
                                                    {plan.name}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {classesLabel(plan.credits)}
                                                    {plan.dailyLimit !== null &&
                                                        `, hasta ${plan.dailyLimit} al día`}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    Si lo validan hoy, vale
                                                    hasta el {plan.lastDay}.
                                                </span>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                Elige un plan.
                                            </p>
                                        )}
                                        <Separator />
                                        <div className="flex items-baseline justify-between gap-4">
                                            <span className="text-sm text-muted-foreground">
                                                Total por transferencia
                                            </span>
                                            <span className="text-2xl font-semibold tabular-nums">
                                                {formatClp(plan?.price ?? 0)}
                                            </span>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            Tu plan parte cuando el estudio
                                            valide el pago.
                                        </p>
                                        <Button
                                            type="submit"
                                            size="lg"
                                            disabled={processing || !plan}
                                        >
                                            {processing && <Spinner />}
                                            Enviar comprobante
                                        </Button>
                                    </CardContent>
                                </Card>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

BuyPlan.layout = (props: { studio: CurrentStudio | null }) => ({
    breadcrumbs: [
        { title: 'Inicio', href: dashboard() },
        ...(props.studio
            ? [
                  { title: 'Mi plan', href: myPlanIndex(props.studio.slug) },
                  { title: 'Comprar plan', href: buyPlan(props.studio.slug) },
              ]
            : []),
    ],
});
