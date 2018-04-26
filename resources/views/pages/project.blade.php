@extends('layouts.app')

@section('title', $title)

@section('nav')
    <div class="ml-auto align-self-center">
        <div class="btn-group">
            <a href="{{ $project['prev'] }}" class="btn {{ ($class == 'danger' ? 'btn-primary' : 'btn-danger') }}"><i class="ti-arrow-left"></i></a>
            <a href="{{ $project['next'] }}" class="btn {{ ($class == 'danger' ? 'btn-primary' : 'btn-danger') }}"><i class="ti-arrow-right"></i></a>
        </div>
    </div>
@endsection

@section('content')
    <div class="bg-light">
        <div class="static-slider5 bg-{{ $class }}-gradiant spacer">
            <div class="p-30"></div>
            <div class="left-right-bg">
                <div class="container">
                    <!-- Row  -->
                    <div class="row justify-content-center ">
                        <!-- Column -->
                        <div class="col-md-8 align-self-center text-center" data-aos="fade-right" data-aos-duration="1200">
                            <h1 class="title text-white">{{ $project['name'] }}</h1>
                            @if (!empty($project['info']))
                                <p class="text-white op-8">{{ $project['info'] }}</p>
                            @endif
                            @if (!empty($project['site']))
                                <a class="btn btn-rounded btn-outline-light btn-md btn-arrow m-t-20" href="{{ $project['site'] }}" target="_blank">
                                    <span>{{ $project['site'] }} <i class="ti-arrow-right"></i></span>
                                </a>
                            @endif
                            @if (!empty($project['versions'] && count($project['versions']) > 1))
                                <p class="op-8 m-t-20">
                                    @foreach ($project['versions'] as $version)
                                        @if ($version['current'])
                                            <a class="btn btn-xs btn-rounded btn-outline-light m-r-10 m-l-10 active" href="{{ $version['link'] }}">version {{ $version['version'] }}</a>
                                        @else
                                            <a class="btn btn-xs btn-rounded btn-outline-light m-r-10 m-l-10" href="{{ $version['link'] }}">version {{ $version['version'] }}</a>
                                        @endif
                                    @endforeach
                                </p>
                            @endif
                        </div>
                        <!-- Column -->
                    </div>
                </div>
            </div>
            <div class="p-30"></div>
        </div>
        <div class="container project-detail">
            <div class="row">
                <div class="col-lg-8">
                    <div class="project-detail__image">
                        <a href="{{ $project['image_full'] }}" data-fancybox><img src="{{ $project['image'] }}"></a>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="testimonial3">
                        <div class="testi3">

                            <div data-aos="fade-left">
                                <div class="card card-shadow">
                                    <div class="card-body">

                                        @foreach ($project['authors'] as $author)

                                           <div class="d-flex no-block align-items-center {{ (!$loop->last ? 'm-b-20' : '') }}">
                                                <span class="thumb-img"><img src="{{ $author['image'] }}" class="circle"/></span>
                                                <div class="m-l-20">
                                                    <h6 class="m-b-0 customer">
                                                        @if ($author['id'] != 1)
                                                            <a href="{{ $author['site'] }}" target="_blank">{{ $author['name'] }}</a>
                                                        @else
                                                            {{ $author['name'] }}
                                                        @endif
                                                    </h6>
                                                    <div class="font-13">
                                                        {{ $author['pivot']['role'] }}
                                                    </div>
                                                </div>
                                            </div>

                                        @endforeach

                                    </div>
                                </div>
                            </div>

                            <div class="card bg-success-gradiant text-white">
                                <div class="card-body">
                                    {{ $project['tags'] }}
                                </div>
                            </div>

                            @if (!empty($project['works']))
                                <div class="card bg-danger-gradiant text-white">
                                    <div class="card-body">
                                        <h3 class="text-white m-b-20">Work</h3>
                                        @foreach ($project['works'] as $work)
                                            <div class="row {{ (!$loop->last ? 'm-b-5' : '') }}">
                                                <div class="col-5">
                                                    <a href="{{ $work['link'] }}" class="font-weight-bold text-light">{{ $work['name'] }}</a>
                                                </div>
                                                <div class="col-7">
                                                    @foreach ($work['years'] as $year)
                                                        {{ $year }}
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-40"></div>
    </div>
@endsection