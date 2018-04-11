<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public $page;
    public $title = ['paShaman'];
    public $template = [];

    public function render()
    {
        $data = array_merge(
            [
                'email'     => 'nikitin696@gmail.com',
                'linkFB'    => 'https://www.facebook.com/paShamanZ',
                'linkVK'    => 'https://vk.com/pashaman',
                'year'      => date('Y'),
                'title'     => implode(" - ", $this->title)
            ],
            $this->template
        );

        return view('pages.' . $this->page, $data);
    }
}
