<?php

namespace App\Http\Controllers;

use App\Actions\ComputePortfolio;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function index(ComputePortfolio $compute): Response
    {
        ['positions' => $positions, 'summary' => $summary] = $compute->forUser(auth()->user());

        return Inertia::render('Portfolio/Index', compact('positions', 'summary'));
    }
}
