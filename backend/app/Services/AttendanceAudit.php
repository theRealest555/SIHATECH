<?php

namespace App\Services;

use App\Models\Rendezvous;
use Illuminate\Support\Facades\DB;

class AttendanceAudit
{
    public function counts(): array
    {
        $cutoff = now()->subMinutes(30);

        return DB::transaction(fn () => [
            'confirmed_needing_review' => Rendezvous::where('statut', 'confirmé')->where('date_heure', '<', $cutoff)->count(),
            'unconfirmed_past_requests' => Rendezvous::where('statut', 'en_attente')->where('date_heure', '<', $cutoff)->count(),
        ]);
    }
}
