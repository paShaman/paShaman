<?php

namespace App\Http\Controllers;

use App\Page;

class ProjectController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function project()
    {
        return $this->render();
    }
}
