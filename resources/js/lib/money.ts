const CLP = new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    maximumFractionDigits: 0,
});

/** Chilean pesos as people write them: «$45.000». Amounts are integers. */
export function formatClp(amount: number): string {
    return CLP.format(amount);
}

const THOUSANDS = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 });

/** The digits of an amount being typed, «20.000» → «20000». */
export function clpDigits(text: string): string {
    return text.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
}

/** An amount being typed, with its thousands dots: «20000» → «20.000». */
export function formatClpDigits(digits: string): string {
    return digits === '' ? '' : THOUSANDS.format(Number(digits));
}
