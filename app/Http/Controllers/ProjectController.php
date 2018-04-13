<?php

namespace App\Http\Controllers;

use App\Project;
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

    /**
     * детальная страница проекта
     *
     * @param $project
     * @return \Illuminate\View\View
     */
    public function project($project)
    {
        $this->title[] = 'Portfolio';
        $this->page = 'project';

        $projectData = (new Project())->getProjectDetail($project);

        if (empty($projectData)) {
            throw new NotFoundHttpException();
        }

        $this->title[] = $projectData['name'];

        $classes = ['info', 'danger', 'success'];

        $this->template['class'] = $classes[rand(0, count($classes) - 1)];
        $this->template['project'] = $projectData;

        return $this->render();
    }
}
