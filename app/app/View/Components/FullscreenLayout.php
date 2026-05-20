<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class FullscreenLayout extends Component
{
    public function __construct(public string $title = 'FP') {}

    public function render(): View
    {
        return view('layouts.fullscreen');
    }
}
