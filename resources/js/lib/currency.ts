export function formatPEN(amount: string | number): string {
    return `S/ ${new Intl.NumberFormat('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(amount))}`;
}
