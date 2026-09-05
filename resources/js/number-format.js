// Match the local amount formats accepted by DatasetRequest. Empty is not zero.
export function parseCurrency(input) {
    let value = input.trim().replace(/Rp| /g, '');
    if (/^[+-]?\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/.test(value)) {
        value = value.replace(/\./g, '');
    }
    value = value.replace(',', '.');
    if (!/^[+-]?\d+(?:\.\d+)?$/.test(value)) return null;
    const amount = Number(value);
    return Number.isFinite(amount) ? amount : null;
}
