<?php

namespace App\Observers;

use App\Models\Avis;
use App\Models\Doctor;
use App\Models\User;

class ReviewObserver
{
    /**
     * Handle the Avis "created" event.
     */
    public function created(Avis $review): void
    {
        if ($review->status === 'approved') {
            $this->updateDoctorRating($review->doctor_id);
        }
    }

    /**
     * Handle the Avis "updated" event.
     */
    public function updated(Avis $review): void
    {
        // Check if status changed to approved
        if ($review->isDirty('status') && $review->status === 'approved') {
            $this->updateDoctorRating($review->doctor_id);
        }

        // Check if rating changed for approved reviews
        if ($review->status === 'approved' && $review->isDirty('rating')) {
            $this->updateDoctorRating($review->doctor_id);
        }
    }

    /**
     * Handle the Avis "deleted" event.
     */
    public function deleted(Avis $review): void
    {
        if ($review->status === 'approved') {
            $this->updateDoctorRating($review->doctor_id);
        }
    }

    /**
     * Update doctor's average rating
     */
    protected function updateDoctorRating(int $doctorUserId): void
    {
        $doctorUser = User::find($doctorUserId);

        if ($doctorUser && $doctorUser->doctor) {
            $doctorUser->doctor->updateAverageRating();
        }
    }
}
