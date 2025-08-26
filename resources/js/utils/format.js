export const currency = (n) => new Intl.NumberFormat('es-PE', {
    style: 'currency', currency: 'PEN', maximumFractionDigits: 2
}).format(Number(n || 0))

export const short = (n) => {
    n = Number(n || 0)
    if (Math.abs(n) >= 1e9) return (n/1e9).toFixed(1)+'B'
    if (Math.abs(n) >= 1e6) return (n/1e6).toFixed(1)+'M'
    if (Math.abs(n) >= 1e3) return (n/1e3).toFixed(1)+'K'
    return String(n)
}
