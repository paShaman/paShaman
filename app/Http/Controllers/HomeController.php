<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;

class HomeController extends BaseController
{
    public function __invoke(Request $request)
    {
        // Режим full — показываем скрытые проекты (аналог ?full=true из референса)
        if ($request->query('full') === 'true' || $request->route()->getName() === 'home.full') {
            $_COOKIE['full'] = true;
        }

        $projects = new Project()->getList();

        foreach ($projects as &$project) {
            $project['tags'] = explode(' ', $project['tags']);
            $project['image'] = 'https://' . $request->getHost() . $project['image'];
        }

        $tagsPre = new Project()->getTags();
        $tags = [];

        foreach ($tagsPre as $key => $count) {
            $tags[] = [
                'name'  => $key,
                'count' => $count,
            ];
        }

        // Счётчики
        $startYear = 2006;
        $counters = [
            'projects'   => Project::where('active', '!=', 0)->count(),
            'experience' => date('Y') - $startYear,
            'cups'       => (int)(((date('Y') - $startYear) * 365 * 100 + date('z') * rand(100, 200)) / 100),
            'countries'  => 27,
        ];

        return Inertia::render('Home', [
            'projects' => $projects,
            'tags'     => $tags,
            'counters' => $counters,
        ]);
    }
}