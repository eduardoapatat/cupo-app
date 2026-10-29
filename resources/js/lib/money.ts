const CLP = new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    maximumFractionDigits: 0,
});

/** Chilean pesos as people write them: «$45.000». Amounts are integers. */
export function formatClp(amount: number): string {
    return CLP.format(amount);
}
