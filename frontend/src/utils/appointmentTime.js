export function formatAppointmentTime(startsAt) {
    // Preserve the server's clinic wall time. Client timezone databases can
    // differ from PHP's database, even when both use the same zone name.
    const wallTime = startsAt.replace(/(?:Z|[+-]\d{2}:\d{2})$/, 'Z');
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC',
    }).format(new Date(wallTime));
}
