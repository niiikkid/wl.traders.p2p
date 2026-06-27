/**
 * @param {{ name?: string|null, email?: string|null, login?: string|null }} user
 */
export function getUserInitials(user = {}) {
    const trimmedName = (user.name || '').trim();

    if (trimmedName) {
        const parts = trimmedName.split(/\s+/).filter(Boolean);

        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }

        return trimmedName.slice(0, 2).toUpperCase();
    }

    const identifier = (user.email || user.login || '').trim();

    if (!identifier) {
        return '?';
    }

    const localPart = identifier.includes('@') ? identifier.split('@')[0] : identifier;
    const cleaned = localPart.replace(/[^a-zA-Z0-9]/g, '');
    const source = cleaned || localPart;

    return source.slice(0, 2).toUpperCase();
}
