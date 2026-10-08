export function apiError(error, fallback) {
    const fieldErrors = error.response?.data?.errors;
    if (fieldErrors) return Object.values(fieldErrors).flat()[0] || fallback;
    return error.response?.data?.message || fallback;
}
