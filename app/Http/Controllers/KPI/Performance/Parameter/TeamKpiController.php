<?php

namespace App\Http\Controllers\KPI\Performance\Parameter;

use App\Http\Controllers\Controller;

class TeamKpiController extends Controller
{
    public function dashboard()
    {
        return inertia('KPI/Performance/Parameter/FormKpi/team/FormKPITeam'); // File: resources/views/team-kpi/dashboard.blade.php
    }

    /**
     * Display Team Management Page
     */
    public function management()
    {
        return inertia('KPI/Performance/Parameter/FormKpi/team/management'); // File: resources/views/team-kpi/management.blade.php
    }

    /**
     * Display Multi KPI Form Page
     */
    public function multiForm()
    {
        return inertia('KPI/Performance/Parameter/FormKpi/team/multiForm'); // File: resources/views/team-kpi/multi-form.blade.php
    }

    /**
     * Display Approval Monitoring Page
     */
    public function approval()
    {
        return inertia('KPI/Performance/Parameter/FormKpi/team/approvalAndMonitoring'); // File: resources/views/team-kpi/approval.blade.php
    }
}
