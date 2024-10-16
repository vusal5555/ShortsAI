<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class UpgradePlanController extends Controller
{

    public function showUpgradeScreen()
    {
        return Inertia::render('Upgrade/index');
    }

    public function upgradeCredits()
    {

    }
}
