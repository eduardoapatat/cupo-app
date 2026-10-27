/**
 * Accessibility props that tie a field to its error message (rendered by
 * InputError with the id `${id}-error`) and to any help text, and mark it
 * invalid so it takes the danger outline.
 */
export function fieldErrorProps(
    id: string,
    error: string | undefined,
    ...describedBy: string[]
): { 'aria-invalid'?: true; 'aria-describedby'?: string } {
    const ids = error ? [...describedBy, `${id}-error`] : describedBy;

    return {
        'aria-invalid': error ? true : undefined,
        'aria-describedby': ids.length > 0 ? ids.join(' ') : undefined,
    };
}

/**
 * Moves the focus to the first field with an error, so keyboard and screen
 * reader users land on what needs fixing. `ids` maps error keys to element
 * ids when they differ.
 */
export function focusFirstError(
    errors: Record<string, string | undefined>,
    ids: Record<string, string> = {},
): void {
    const firstKey = Object.keys(errors)[0];

    if (firstKey) {
        document.getElementById(ids[firstKey] ?? firstKey)?.focus();
    }
}
