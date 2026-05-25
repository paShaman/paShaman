<?php

namespace App\Http\Controllers;

use App\Page;
use App\Project;
use Illuminate\Http\Request;

class ApiController extends Controller
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

    public function getProjects(Request $request)
    {
        if ($request->query('full') === 'true') {
            setcookie('full', true, time() + 60*10); //куку на 10 минут
            $_COOKIE['full'] = true;
        }

        $projects = (new Project())->getList();
        $tagsPre = (new Project())->getTags();

        $tags = [];

        foreach ($tagsPre as $key => $count) {
            $tags[] = [
                'name'  => $key,
                'count' => $count,
            ];
        }

        foreach ($projects as &$project) {
            $project['tags'] = explode(' ', $project['tags']);
            $project['image'] = 'https://' . $_SERVER['HTTP_HOST'] . $project['image'];
        }

        return json_encode([
            'projects' => $projects,
            'tags'     => $tags,
        ]);
    }

    public function getCounters()
    {
        $startYear = 2006;

        $counters = [
            'projects'   => Project::where('active', '!=', 0)->count(),
            'experience' => date('Y') - $startYear,
            'cups'       => (int)(((date('Y') - $startYear) * 365 * 100 + date('z') * rand(100, 200)) / 100),
            'countries'  => 27,
        ];

        return json_encode($counters);
    }

    public function getProject($project)
    {
        $projectData = (new Project())->getProjectDetail($project);

        if (empty($projectData)) {
            return json_encode(false);
        }

        $currentVersion = false;

        if (count($projectData->versions) > 1) {
            foreach ($projectData->versions as $version) {
                if ($version['current'] === true) {
                    $currentVersion = $version['version'];
                }
            }
        }

        $project = array_merge($projectData->toArray(), [
            'authors'    => $projectData->authors,
            'image_full' => $projectData->image_full,
            'prev'       => $projectData->prev,
            'next'       => $projectData->next,
            'works'      => $projectData->works,
            'versions'   => array_reverse($projectData->versions),
            'years'      => array_reverse($projectData->years),
            'current_version' => $currentVersion,
            'host'       => 'https://' . $_SERVER['HTTP_HOST'],
        ]);

        $project['tags'] = array_filter(explode(' ', $project['tags']));

        return json_encode($project);
    }
}
