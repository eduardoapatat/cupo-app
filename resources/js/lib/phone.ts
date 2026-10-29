/** +56912345678 → +56 9 1234 5678, as people read it. */
export function formatPhone(phone: string | null): string {
    const match = phone?.match(/^\+56(9)(\d{4})(\d{4})$/);

    return match ? `+56 ${match[1]} ${match[2]} ${match[3]}` : (phone ?? '');
}
