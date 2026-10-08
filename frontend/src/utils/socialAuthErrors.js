const messages = {
    provider_unavailable: 'This social sign-in option is currently unavailable. Use email and password instead.',
    unsupported_provider: 'This sign-in provider is not supported. Use email and password instead.',
    redirect_failed: 'Social sign-in could not start. Try again or use email and password.',
    authentication_failed: 'Social sign-in could not be completed. Try again or use email and password.',
    cancelled: 'Social sign-in was cancelled. You can try again or use email and password.',
    session_expired: 'Your social sign-in session expired. Start sign-in again from this page.',
    verified_email_required: 'This provider could not confirm a verified email address. Use email registration or an existing linked account.',
    account_link_required: 'An account already uses this email. Sign in with its existing method or reset its password. Social accounts are not linked automatically.',
    account_disabled: 'This account is unavailable. Contact support if you need help restoring access.',
};

export function socialAuthError(code) {
    if (!code) return '';
    return Object.hasOwn(messages, code) ? messages[code] : messages.authentication_failed;
}
