@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="static-slider1">
        <div class="container">
            <h1>404</h1>
            <!-- Row  -->
            <div class="row ">
                <!-- Column -->
                <div class="col-lg-9 align-self-center" data-aos="fade-right" data-aos-duration="1200">
                    <h2 class="title font-light text-animate">This page <span class="text-info-gradiant font-bold typewrite" data-period="2000" data-type='[ "was not found", "is lost", "never existed" ]'></span></h2>
                </div>
                <!-- Column -->
            </div>

            <br><br><br><br>
            <div class="text-center">
                <a href="/" class="btn btn-danger btn-md btn-arrow"><span>Portfolio <i class="ti-arrow-left"></i></span></a>
            </div>
        </div>
    </div>
@endsection