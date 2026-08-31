<?php

namespace App\Cobranzas\Models\Observers;

use App\Cobranzas\Models\PagoCuota;

class PagoCuotaObserver
{
    public function created(PagoCuota $pagoCuota): void
    {
        $pagoCuota->cuota->recalcularEstado();
    }

    public function deleted(PagoCuota $pagoCuota): void
    {
        $pagoCuota->cuota->recalcularEstado();
    }
}
