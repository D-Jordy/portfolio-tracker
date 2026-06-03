<?php

namespace App\Http\Controllers;

use App\Actions\ComputePortfolio;
use App\Actions\ComputePortfolioHistory;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    public function index(ComputePortfolio $compute, ComputePortfolioHistory $history): Response
    {
        $user = auth()->user();

        ['positions' => $positions, 'summary' => $summary] = $compute->forUser($user);
        $chartData = $history->forUser($user);

        return Inertia::render('Portfolio/Index', compact('positions', 'summary', 'chartData'));
    }
}
