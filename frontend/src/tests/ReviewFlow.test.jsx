// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route, Link } from 'react-router-dom';
import ReviewPage from '../pages/patient/ReviewPage';
import ReviewModerationPage from '../pages/admin/ReviewModerationPage';
import * as patients from '../services/patientService';
import * as admins from '../services/adminService';
vi.mock('../services/patientService', () => ({ getAppointmentReview: vi.fn(), submitAppointmentReview: vi.fn() }));
vi.mock('../services/adminService', () => ({ getModerationReviews: vi.fn(), moderateReview: vi.fn() }));
const review = { id: 7, appointment_id: 9, rating: 4, comment: 'Helpful communication', status: 'pending', patient_name: 'Preview Patient', doctor_name: 'Preview Doctor', reason: null };
const context = { appointment_id: 9, doctor_name: 'Preview Doctor', starts_at: '2026-10-01T09:00:00+00:00', can_submit: true, review: null };
const list = (rows = [review]) => ({ data: { data: rows, meta: { total: rows.length, current_page: 1, last_page: 1 } } });
const showPatient = () => render(<MemoryRouter initialEntries={['/patient/appointments/9/review']}><Routes><Route path="/patient/appointments/:appointmentId/review" element={<ReviewPage />} /></Routes></MemoryRouter>);
beforeEach(() => { vi.resetAllMocks(); patients.getAppointmentReview.mockResolvedValue({ data: { data: context } }); patients.submitAppointmentReview.mockResolvedValue({ data: { review } }); admins.getModerationReviews.mockResolvedValue(list()); admins.moderateReview.mockResolvedValue({}); });
afterEach(cleanup);
it('submits patient feedback and shows pending moderation without claiming publication', async () => {
    const user = userEvent.setup(); showPatient();
    await user.selectOptions(await screen.findByLabelText('Rating'), '4');
    await user.type(screen.getByLabelText('Feedback (optional)'), 'Helpful communication');
    await user.click(screen.getByRole('button', { name: 'Submit for moderation' }));
    await screen.findByRole('heading', { name: 'Review status: pending' });
    expect(patients.submitAppointmentReview).toHaveBeenCalledWith('9', { rating: 4, comment: 'Helpful communication' });
    expect(screen.queryByRole('button', { name: 'Submit for moderation' })).toBeNull();
});
it('shows existing rejection reason and never permits a duplicate submission', async () => {
    patients.getAppointmentReview.mockResolvedValue({ data: { data: { ...context, can_submit: false, review: { ...review, status: 'rejected', reason: 'Private details' } } } });
    showPatient(); await screen.findByText('Moderation reason: Private details');
    expect(screen.queryByLabelText('Rating')).toBeNull();
});
it('handles ineligible visits and submission failures without a false success', async () => {
    patients.submitAppointmentReview.mockRejectedValue(new Error('offline'));
    const user = userEvent.setup(); showPatient();
    await user.click(await screen.findByRole('button', { name: 'Submit for moderation' }));
    await screen.findByRole('alert'); expect(screen.queryByText('Review status: pending')).toBeNull();
});
it('requires an explicit moderation confirmation with the loaded status', async () => {
    const user = userEvent.setup(); render(<ReviewModerationPage />);
    await user.click(await screen.findByRole('button', { name: 'Approve review #7' }));
    expect(admins.moderateReview).not.toHaveBeenCalled();
    await user.click(screen.getByRole('button', { name: 'Confirm decision' }));
    await screen.findByText('Moderation saved.');
    expect(admins.moderateReview).toHaveBeenCalledWith(7, { action: 'approve', expected_status: 'pending' });
});
it('sends rejection reasons and renders review text safely', async () => {
    const user = userEvent.setup(); admins.getModerationReviews.mockResolvedValue(list([{ ...review, comment: '<img src=x onerror=alert(1)>' }]));
    render(<ReviewModerationPage />); await screen.findByText('<img src=x onerror=alert(1)>');
    expect(document.querySelector('main img')).toBeNull();
    await user.click(screen.getByRole('button', { name: 'Reject review #7' }));
    await user.type(screen.getByLabelText('Reason'), 'Private details'); await user.click(screen.getByRole('button', { name: 'Confirm decision' }));
    await waitFor(() => expect(admins.moderateReview).toHaveBeenCalledWith(7, { action: 'reject', expected_status: 'pending', reason: 'Private details' }));
});
it('refreshes a conflicting moderation response without automatically resubmitting', async () => {
    const user = userEvent.setup(); admins.moderateReview.mockRejectedValue({ response: { status: 409, data: { message: 'This review changed.' } } });
    render(<ReviewModerationPage />); await user.click(await screen.findByRole('button', { name: 'Approve review #7' }));
    await user.click(screen.getByRole('button', { name: 'Confirm decision' }));
    await screen.findByRole('alert'); await waitFor(() => expect(admins.getModerationReviews).toHaveBeenCalledTimes(2));
    expect(admins.moderateReview).toHaveBeenCalledTimes(1);
});
it('ignores an old moderation-list response after the status filter changes', async () => {
    const user = userEvent.setup(); let complete;
    admins.getModerationReviews.mockReturnValueOnce(new Promise(resolve => { complete = resolve; })).mockResolvedValueOnce(list([]));
    render(<ReviewModerationPage />); await user.selectOptions(screen.getByLabelText('Status'), 'approved');
    await screen.findByText('No reviews match this status.'); complete(list());
    await waitFor(() => expect(screen.queryByText('Helpful communication')).toBeNull());
});

it('keeps a late submission response attached to its original visit', async () => {
    const user = userEvent.setup(); let complete;
    patients.getAppointmentReview.mockResolvedValueOnce({ data: { data: context } }).mockResolvedValueOnce({ data: { data: { ...context, appointment_id: 10, doctor_name: 'Next Doctor' } } });
    patients.submitAppointmentReview.mockReturnValueOnce(new Promise(resolve => { complete = resolve; }));
    render(<MemoryRouter initialEntries={['/patient/appointments/9/review']}><Link to="/patient/appointments/10/review">Next visit</Link><Routes><Route path="/patient/appointments/:appointmentId/review" element={<ReviewPage />} /></Routes></MemoryRouter>);
    await user.click(await screen.findByRole('button', { name: 'Submit for moderation' }));
    await user.click(screen.getByRole('link', { name: 'Next visit' })); await screen.findByText('Next Doctor');
    complete({ data: { review } });
    await waitFor(() => expect(screen.queryByRole('heading', { name: 'Review status: pending' })).toBeNull());
    expect(screen.getByRole('button', { name: 'Submit for moderation' }).disabled).toBe(false);
});
