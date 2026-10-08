// @vitest-environment jsdom
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import AvailabilityPage from '../pages/doctor/AvailabilityPage';
import DocumentsPage from '../pages/doctor/DocumentsPage';
import * as service from '../services/doctorService';
vi.mock('../services/doctorService', () => ({
    getDoctorAvailability: vi.fn(), saveDoctorSchedule: vi.fn(), createDoctorLeave: vi.fn(), deleteDoctorLeave: vi.fn(),
    getDoctorDocuments: vi.fn(), uploadDoctorDocument: vi.fn(), deleteDoctorDocument: vi.fn(), downloadDoctorDocument: vi.fn(),
}));
const availability = { schedule_revision: 'schedule-revision', schedule: { lundi: ['09:00-12:00'] }, leaves: [], timezone: 'Africa/Casablanca', today: '2100-01-01', can_edit: true };
const document = { id: 1, original_name: 'licence.pdf', type: 'licence', status: 'pending' };
beforeEach(() => {
    vi.resetAllMocks();
    service.getDoctorAvailability.mockResolvedValue({ data: { data: availability } });
    service.getDoctorDocuments.mockResolvedValue({ data: { documents: [document] } });
});
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
it('saves real French schedule keys and preserves the draft when bookings conflict', async () => {
    const user = userEvent.setup(); render(<AvailabilityPage />);
    const monday = await screen.findByLabelText('Monday');
    await user.clear(monday); await user.type(monday, '14:00-17:00');
    service.saveDoctorSchedule.mockRejectedValue({ response: { status: 409, data: { message: 'Existing booking conflict.' } } });
    await user.click(screen.getByRole('button', { name: 'Save schedule' }));
    expect(service.saveDoctorSchedule.mock.calls[0][1]).toBe('schedule-revision');
    expect(service.saveDoctorSchedule.mock.calls[0][0].lundi).toEqual(['14:00-17:00']);
    expect((await screen.findByRole('alert')).textContent).toContain('Existing booking conflict.');
    expect(monday.value).toBe('14:00-17:00');
    expect(screen.queryByText('Weekly schedule saved.')).toBeNull();
});
it('adds inclusive leave dates and removes leave only after confirmation', async () => {
    const user = userEvent.setup(); render(<AvailabilityPage />);
    await screen.findByLabelText('Monday');
    await user.type(screen.getByLabelText('Start date'), '2100-01-03');
    await user.type(screen.getByLabelText('End date'), '2100-01-04');
    await user.type(screen.getByLabelText('Reason (optional, private)'), 'Conference');
    const leave = { id: 3, start_date: '2100-01-03', end_date: '2100-01-04', reason: 'Conference' };
    service.createDoctorLeave.mockResolvedValue({});
    service.getDoctorAvailability.mockResolvedValue({ data: { data: { ...availability, leaves: [leave] } } });
    await user.click(screen.getByRole('button', { name: 'Add leave' }));
    await screen.findByText('Leave added.');
    expect(service.createDoctorLeave).toHaveBeenCalledWith({ start_date: '2100-01-03', end_date: '2100-01-04', reason: 'Conference' });
    await user.click(screen.getByRole('button', { name: 'Remove leave' }));
    expect(service.deleteDoctorLeave).not.toHaveBeenCalled();
    service.deleteDoctorLeave.mockResolvedValue({});
    await user.click(screen.getByRole('button', { name: 'Confirm removal' }));
    await waitFor(() => expect(service.deleteDoctorLeave).toHaveBeenCalledWith(3));
});
it('disables editing for doctors awaiting verification', async () => {
    service.getDoctorAvailability.mockResolvedValue({ data: { data: { ...availability, can_edit: false } } });
    render(<AvailabilityPage />); const monday = await screen.findByLabelText('Monday');
    expect(monday.closest('fieldset').disabled).toBe(true);
});
it('uploads a multipart credential and shows the server review state', async () => {
    const user = userEvent.setup(); render(<DocumentsPage />);
    await screen.findByText('licence.pdf');
    const file = new File(['%PDF-1.4'], 'diploma.pdf', { type: 'application/pdf' });
    await user.selectOptions(screen.getByLabelText('Document type'), 'diplome');
    await user.upload(screen.getByLabelText('File'), file);
    expect(screen.getByLabelText('File').files.length).toBe(1);
    service.uploadDoctorDocument.mockResolvedValue({});
    service.getDoctorDocuments.mockResolvedValue({ data: { documents: [{ ...document, original_name: file.name, type: 'diplome' }] } });
    // jsdom checks its internal FileList, while user-event supplies a wrapper FileList.
    fireEvent.submit(screen.getByRole('button', { name: 'Upload document' }).closest('form'));
    await screen.findByText('Document uploaded and awaiting review.');
    expect(service.uploadDoctorDocument.mock.calls[0][0].get('file')).toBe(file);
    expect(service.uploadDoctorDocument.mock.calls[0][0].get('type')).toBe('diplome');
    expect(screen.getByText('Diploma · Awaiting review')).toBeTruthy();
});
it('keeps the selected file after upload failure and does not claim success', async () => {
    const user = userEvent.setup(); render(<DocumentsPage />); await screen.findByText('licence.pdf');
    const file = new File(['%PDF'], 'test.pdf', { type: 'application/pdf' });
    await user.upload(screen.getByLabelText('File'), file);
    service.uploadDoctorDocument.mockRejectedValue({ response: { data: { errors: { file: ['Invalid PDF.'] } } } });
    // jsdom checks its internal FileList, while user-event supplies a wrapper FileList.
    fireEvent.submit(screen.getByRole('button', { name: 'Upload document' }).closest('form'));
    expect((await screen.findByRole('alert')).textContent).toContain('Invalid PDF.');
    expect(screen.getByLabelText('File').files[0]).toBe(file);
});
it('hides delete for approved credentials and downloads through the private API', async () => {
    service.getDoctorDocuments.mockResolvedValue({ data: { documents: [{ ...document, status: 'approved' }] } });
    const user = userEvent.setup(); render(<DocumentsPage />); await screen.findByText('licence.pdf');
    expect(screen.queryByRole('button', { name: 'Delete licence.pdf' })).toBeNull();
    const blob = new Blob(['%PDF'], { type: 'application/pdf' });
    service.downloadDoctorDocument.mockResolvedValue({ data: blob });
    URL.createObjectURL = vi.fn(() => 'blob:private'); URL.revokeObjectURL = vi.fn();
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
    await user.click(screen.getByRole('button', { name: 'Download licence.pdf' }));
    await waitFor(() => expect(click).toHaveBeenCalled());
    expect(service.downloadDoctorDocument).toHaveBeenCalledWith(1);
    expect(URL.createObjectURL).toHaveBeenCalledWith(blob);
});
it('shows a private download failure without opening a public file URL', async () => {
    const user = userEvent.setup(); render(<DocumentsPage />); await screen.findByText('licence.pdf');
    service.downloadDoctorDocument.mockRejectedValue({ response: { data: { message: 'Document unavailable.' } } });
    await user.click(screen.getByRole('button', { name: 'Download licence.pdf' }));
    expect((await screen.findByRole('alert')).textContent).toContain('Document unavailable.');
});
it('requires explicit confirmation before deleting a document', async () => {
    const user = userEvent.setup(); render(<DocumentsPage />); await screen.findByText('licence.pdf');
    await user.click(screen.getByRole('button', { name: 'Delete licence.pdf' }));
    expect(service.deleteDoctorDocument).not.toHaveBeenCalled();
    service.deleteDoctorDocument.mockResolvedValue({});
    service.getDoctorDocuments.mockResolvedValue({ data: { documents: [] } });
    await user.click(screen.getByRole('button', { name: 'Confirm deletion' }));
    await screen.findByText('Document removed. Private file cleanup is queued.');
    expect(service.deleteDoctorDocument).toHaveBeenCalledWith(1);
    expect(screen.getByText('No documents uploaded.')).toBeTruthy();
});
it('explains missing credential files and disables download', async () => {
    service.getDoctorDocuments.mockResolvedValue({ data: { documents: [{ ...document, file_available: false }] } });
    render(<DocumentsPage />);
    await screen.findByText('licence.pdf');
    expect(screen.getByText(/This file is unavailable/)).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Download licence.pdf' }).disabled).toBe(true);
    expect(service.downloadDoctorDocument).not.toHaveBeenCalled();
});
