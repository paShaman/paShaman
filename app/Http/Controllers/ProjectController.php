<?php

namespace App\Http\Controllers;

use App\Page;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        throw new NotFoundHttpException();
        return $this->render();
    }
}
