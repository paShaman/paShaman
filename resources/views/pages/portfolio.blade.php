@extends('layouts.app')

@section('title', $title)

@section('content')
<!-- ============================================================== -->
<!-- Static Slider 1  -->
<!-- ============================================================== -->
<div class="static-slider1">
    <div class="container">
        <!-- Row  -->
        <div class="row ">
            <!-- Column -->
            <div class="col-lg-9 align-self-center" data-aos="fade-right" data-aos-duration="1200">
                <h2 class="title font-light text-animate">I'm Pavel Nikitin, an <span class="font-bold">Backend</span> & <span class="font-bold">Frontend Developer</span>, Making  <span class="text-info-gradiant font-bold typewrite" data-period="2000" data-type='[ "Web Applications", "Web Development", "Layouts" ]'></span></h2>
            </div>
            <!-- Column -->

        </div>
    </div>
</div>
<!-- ============================================================== -->
<!-- End Static Slider 1  -->
<!-- ============================================================== -->
<div class="bg-info spacer">
    <div class="container">
        <!-- Row -->
        <div class="row client-box">
            <!-- column  -->
            <div class="col-lg-3 col-6">
                <div class="d-flex no-block">
                    <div class="display-5 text-white op-7"><i class="icon-Project"></i></div>
                    <div class="m-l-20">
                        <h1 class="font-light text-white counter m-b-0">{{ count($projects) }}</h1>
                        <h6 class="text-white font-13 text-uppercase op-7">works done</h6>
                    </div>
                </div>
            </div>
            <!-- column  -->
            <!-- column  -->
            <div class="col-lg-3 col-6">
                <div class="d-flex no-block">
                    <div class="display-5 text-white op-7"><i class="icon-Timer"></i></div>
                    <div class="m-l-20">
                        <h1 class="font-light text-white counter m-b-0">{{ $experience }}</h1>
                        <h6 class="text-white font-13 text-uppercase op-7">years of experience</h6>
                    </div>
                </div>
            </div>
            <!-- column  -->
            <!-- column  -->
            <div class="col-lg-3 col-6">
                <div class="d-flex no-block">
                    <div class="display-5 text-white op-7"><i class="icon-Coffee"></i></div>
                    <div class="m-l-20">
                        <h1 class="font-light text-white counter m-b-0">{{ $cups }}</h1>
                        <h6 class="text-white font-13 text-uppercase op-7">CUPS OF COFFE</h6>
                    </div>
                </div>
            </div>
            <!-- column  -->
            <!-- column  -->
            <div class="col-lg-3 col-6">
                <div class="d-flex no-block">
                    <div class="display-5 text-white op-7"><i class="icon-Globe-2"></i></div>
                    <div class="m-l-20">
                        <h1 class="font-light text-white counter m-b-0">{{ $countries }}</h1>
                        <h6 class="text-white font-13 text-uppercase op-7">countries visited</h6>
                    </div>
                </div>
            </div>
            <!-- column  -->
        </div>
    </div>
</div>
<!-- ============================================================== -->
<!-- Portfolio  -->
<!-- ============================================================== -->
<div class="portfolio1 up m-b-40 m-t-40 p-t-30">
    <div class="container">
        <div class="toggle-filter">
            <span class="btn btn-danger" onclick="toggleFilter($(this))">Toggle filter</span>
        </div>
        <!-- Tittle and filter  -->
        <div class="filter-row">
            <div class="d-flex align-items-center">
                <div class="filterby">
                    <a href="javascript:void(0)" class="active" data-filter="*">All</a>
                    @foreach ($tags as $tag => $cnt)
                        @if ($loop->index < 5)
                            <a href="javascript:void(0)" data-filter="{{ $tag }}">{{ $tag }}</a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        <div class="filter-row" style="display: none">
            <div class="d-flex align-items-center">
                <h3 class="filterby">
                    <span class="label label-danger" data-filter="*">All - <b>{{ count($projects) }}</b></span>
                    @foreach ($tags as $tag => $cnt)
                        @if ($cnt > 50)
                            <span class="label label-success" data-filter="{{ $tag }}">{{ $tag }} - <b>{{ $cnt }}</b></span>
                        @elseif ($cnt > 10)
                            <span class="label label-warning" data-filter="{{ $tag }}">{{ $tag }} - <b>{{ $cnt }}</b></span>
                        @else
                            <span class="label label-light-info" data-filter="{{ $tag }}">{{ $tag }} - <b>{{ $cnt }}</b></span>
                        @endif
                    @endforeach
                </h3>
            </div>
        </div>
        <!-- End Tittle and filter  -->
        <!-- Card Columns -->
        <div class="row portfolio-box">
            @foreach ($projects as $project)
                <!-- Columns -->
                    <div class="item col-lg-4 col-md-6 filter {{ $project['tags'] }}">
                        <div class="overlay-box">
                            <a href="{{ $project['link'] }}" class="img-ho"><span style="background-image: url({{ $project['image'] }})"></span></a>
                            <a href="{{ $project['link'] }}" class="d-flex port-text align-items-center">
                                <div class="item__info">
                                    <span class="item__date">
                                        {{ $project['date'] }}
                                    </span>
                                    <h5>{{ $project['name'] }}</h5>
                                    <span class="item__tags">{{ $project['tags'] }}</span>
                                </div>
                            </a>
                        </div>
                    </div>
                <!-- Columns -->
            @endforeach
        </div>
        <!-- End Card Columns -->
    </div>
</div>
<!-- ============================================================== -->
<!-- End Portfolio 1  -->
<!-- ============================================================== -->
@endsection