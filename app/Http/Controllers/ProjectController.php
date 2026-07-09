<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Inertia\Inertia;

class ProjectController extends BaseController
{
    public function show(Request $request, $slug)
    {
        $showHidden = (bool) $request->cookie('full');
        $projectData = new Project()->getProjectDetail($slug, $showHidden);

        if (empty($projectData)) {
            abort(404);
        }

        // Если проект скрытый (active=2) а куки full нет — 404
        if ($projectData->active == 2 && !$showHidden) {
            abort(404);
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
            'authors'         => $projectData->authors,
            'image_full'      => $projectData->image_full,
            'prev'            => $projectData->prev,
            'next'            => $projectData->next,
            'works'           => $projectData->works,
            'versions'        => array_reverse($projectData->versions),
            'years'           => array_reverse($projectData->years),
            'current_version' => $currentVersion,
            'host'            => 'https://' . $request->getHost(),
        ]);

        $project['tags'] = array_filter(explode(' ', $project['tags']));

        return Inertia::render('Project', [
            'project' => $project,
        ]);
    }
}